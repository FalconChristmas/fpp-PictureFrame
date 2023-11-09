
<script>
var config = {};          // Plugin configuration

function InsertSenderRow() {
    $('#sendersBody').append("<tr><td valign='middle'>  <div class='rowGrip'> <i class='rowGripIcon fpp-icon-grip'></i> </div> </td>" +
                    "<td><input type='text' class='email' size=32 maxlength=64 value='' /></td>" +
                    "<td><input type='text' class='folder' size=32 maxlength=64 list='imageFolders' /></td>" +
                    "<td><input type='text' class='note' size=32 maxlength=64 value='' /></td>" +
                    "<td onClick='$(this).parent().remove();'><span style='cursor: pointer;'><b>[X]</b></span></td>" +
                    "</tr>");
}

function InsertFolderRow() {
    $('#foldersBody').append("<tr><td><input type='text' class='folder' size=32 maxlength=64 value='' /></td></tr>");
}

function CheckForNewImages() {
    var options = {
        id: 'fetchImagesDialog',
        title: 'Fetch New Images',
        body: "<textarea style='width: 99%; height: 500px;' disabled id='fetchImagesText'></textarea>",
        noClose: true,
        keyboard: false,
        backdrop: 'static',
        footer: '',
        buttons: {
            'Close': {
                id: 'fetchImagesCloseButton',
                click: function() { CloseModalDialog('fetchImagesDialog'); },
                disabled: true,
                class: 'btn-success'
            }
        }
    };

    $('#fetchImagesCloseButton').prop('disabled', true);
    DoModalDialog(options);

    StreamURL('runEventScript.php?scriptName=CheckForNewPictureFrameImages.sh&nohtml=1', 'fetchImagesText', 'FetchImagesDone');
}

function FetchImagesDone() {
    $('#fetchImagesCloseButton').prop('disabled', false);
    EnableModalDialogCloseButton('fetchImagesDialog');
}

function GeneratePlaylist() {
    var duration = 0;
    var pl = {};
    pl.name = "Slideshow-Example";
    pl.version = 3;
    pl.repeat = 0;
    pl.loopCount = 0;
    pl.empty = false;
    pl.desc = "Slideshow-Example";
    pl.random = 0;

    var leadIn = [];
    var mainPlaylist = [];
    var leadOut = [];
    var playlistInfo = {};

    var f = {};
    f.type = "command";
    f.enabled = 1;
    f.playOnce = 0;
    f.command = "Run Script";
    f.args = ["CheckForNewPictureFrameImages.sh", "", ""];
    leadIn.push(f);

    var mon_on = {};
    mon_on.type = "command";
    mon_on.enabled = 1;
    mon_on.playOnce = 0;
    mon_on.command = "Run Script";
    mon_on.args = ["pf-monitor_on.sh", "", ""];
    leadIn.push(mon_on);

    $('#foldersBody > tr').each(function() {
        var folder = $(this).find('.folder').val();

        var i = {};
        i.type = "image";
        i.enabled = 1;
        i.playOnce = 0;
        i.imagePath = "\/home\/fpp\/media\/images\/" + folder + "\/";
        i.modelName = 'fb0';
        mainPlaylist.push(i);

        var p = {};
        p.type = "pause";
        p.enabled = 1;
        p.playOnce = 0;
        p.duration = 60;
        mainPlaylist.push(p);

        duration += 60;
    });

    if (duration == 0) {
        var i = {};
        i.type = "image";
        i.enabled = 1;
        i.playOnce = 0;
        i.imagePath = "\/home\/fpp\/media\/images\/";
        i.modelName = 'fb0';
        mainPlaylist.push(i);

        var p = {};
        p.type = "pause";
        p.enabled = 1;
        p.playOnce = 0;
        p.duration = 60;
        mainPlaylist.push(p);

        duration += 60;
    }

    var mon_off = {};
    mon_off.type = "command";
    mon_off.enabled = 1;
    mon_off.playOnce = 0;
    mon_off.command = "Run Script";
    mon_off.args = ["pf-monitor_off.sh", "", ""];
    leadOut.push(mon_off);

    playlistInfo.total_duration = duration;
    playlistInfo.total_items = leadIn.length + mainPlaylist.length + leadOut.length;

    pl.leadIn = leadIn;
    pl.mainPlaylist = mainPlaylist;
    pl.leadOut = leadOut;
    pl.playlistInfo = playlistInfo;

    var str = JSON.stringify(pl, true);

    $.ajax({
        url: "api/playlist/Slideshow-Example",
        type: 'POST',
        contentType: 'application/json',
        data: str,
        async: false,
        dataType: 'json',
        success: function (data) {
            $.jGrowl("Playlist Created", { themeState: 'success' });
            location.href = 'playlists.php?playlist=Slideshow-Example';
        },
        error: function (...args) {
            DialogError('Unable to save playlist', "Error: Unable to save playlist." + show_details(args));
        }
    });

}

function UpdateFolderDatalist() {
    var options = "";
    $('#foldersBody > tr').each(function() {
        var folder = $(this).find('.folder').val();
        options += "<option value='" + folder + "'>" + folder + "</option>";
    });
    $('#imageFolders').html(options);
}

function SaveFolders() {
    var folders = [];

    $('#foldersBody > tr').each(function() {
        var folder = $(this).find('.folder').val();
        if (folder != '') {
            Post('api/dir/Images/' + folder, true, '');
            folders.push(folder);
        }
    });

    UpdateFolderDatalist();
}

function SavePictureFrameConfig() {
    var config = {};
    var senders = [];
    $('#sendersBody > tr').each(function() {
        var sender = {};
        sender.email = $(this).find('.email').val().trim();
        sender.folder = $(this).find('.folder').val().trim();
        sender.note = $(this).find('.note').val().trim();
        senders[senders.length] = sender;

        if (sender.folder != '')
            Post('api/dir/Images/' + sender.folder, true, '');
    });
    config.senders = senders;

    var configStr = JSON.stringify(config);

    $.post('/api/configfile/plugin.fpp-PictureFrame.json', configStr).done(function(data) {
        $.jGrowl('FPP Picture Frame Config Saved');
    }).fail(function() {
        alert('Error, could not save plugin.fpp-PictureFrame.json config file.');
    });
}

function LoadConfig() {
    $.ajax({
        url: '/api/configfile/plugin.fpp-PictureFrame.json',
        async: false,
        success: function(data) {
            config = data;

            var rows = "";
            for (var x = 0; x < config.senders.length; x++) {
                rows += "<tr><td valign='middle'>  <div class='rowGrip'> <i class='rowGripIcon fpp-icon-grip'></i> </div> </td>" +
                    "<td><input type='text' class='email' size=32 maxlength=64 value='" + config.senders[x].email + "' /></td>" +
                    "<td><input type='text' class='folder' size=32 maxlength=64 value='" + config.senders[x].folder + "' list='imageFolders' /></td>" +
                    "<td><input type='text' class='note' size=32 maxlength=64 value='" + config.senders[x].note + "' /></td>" +
                    "<td onClick='$(this).parent().remove();'><span style='cursor: pointer;'><b>[X]</b></span></td>" +
                    "</tr>";
            }
            $('#sendersBody').html(rows);
        }
    });

}

function DeleteSelectedFolder() {
    if (folderTableInfo.selected >= 0) {
        var folder = $('#foldersBody .fppTableSelectedEntry').find('.folder').val();

        $('#foldersBody .fppTableSelectedEntry').remove();
        folderTableInfo.selected = -1;
        SetButtonState("#btnDeleteFolder", "disable");

        if (folder != '') {
            $.ajax({
                url: 'api/dir/Images/' + folder,
                type: 'DELETE',
                async: true,
                dataType: 'json',
                error: function () {
                    $.jGrowl('Unable to delete folder ' + folder, { themeState: 'danger' });
                }
            });
        }
    }

    UpdateFolderDatalist();
}

function DeleteSelectedSender() {
    if (senderTableInfo.selected >= 0) {
        $('#sendersBody .fppTableSelectedEntry').remove();
        senderTableInfo.selected = -1;
        SetButtonState("#btnDeleteSender", "disable");
    }
}

var folderTableInfo = {
    tableName: "foldersTable",
    selected:  -1,
    enableButtons: [ "btnDeleteFolder" ],
    disableButtons: [],
    sortable: 0
};

var senderTableInfo = {
    tableName: "sendersTable",
    selected:  -1,
    enableButtons: [ "btnDeleteSender" ],
    disableButtons: [],
    sortable: 1
};

$(document).ready(function() {
    SetupSelectableTableRow(folderTableInfo);
    SetupSelectableTableRow(senderTableInfo);
    LoadConfig();   
    $(document).tooltip();
});

</script>


<div id="warningsRow" class="alert alert-danger"><div id="warningsTd"><div id="warningsDiv"></div></div></div>
<div id="global" class="settings">
    <fieldset>
        <div class="row tablePageHeader">
            <div class="col-md"><h3>Picture Folders</h3></div>
            <div class="col-md-auto ms-lg-auto">
                <div class="form-actions">
                    <input type=button value='Delete' onClick='DeleteSelectedFolder();' data-btn-enabled-class="btn-outline-danger" id='btnDeleteFolder' class='disableButtons'>

                    <button class='buttons btn-outline-success' value='Add' onClick='InsertFolderRow();'><i class="fas fa-plus"></i> Add</button>
                    <input type='button' class='buttons btn-success' value='Save' onClick='SaveFolders();'>
                </div>
            </div>
        </div>

        <div class='fppTableWrapper fppTableWrapperAsTable'>
            <div class='fppTableContents'>
                <table id='foldersTable' class='fppSelectableRowTable'>
                    <tbody id='foldersBody'>
<?
$imageFolders = array();
$imageDir = '/home/fpp/media/images';
foreach (scandir($imageDir) as $fileName) {
    if ($fileName != '.' && $fileName != '..') {
        if (is_dir($imageDir . '/' . $fileName)) {
            array_push($imageFolders, $fileName);
        }
    }
}

foreach ($imageFolders as $dirName) {
    printf( "<tr><td><input type='text' class='folder' size=32 maxlength=64 value='%s' /></td></tr>", $dirName);
}
?>
                    </tbody>
                </table>
                <b>NOTE: Folders are automatically deleted when the Delete button is used, you do not need to use the Save button.  You must Save the folder list before folder names will automatically appear in the folder column of the sender list.</b>
            </div>
        </div>
        <br>
        <hr>
        <br>

        <div class="row tablePageHeader">
            <div class="col-md"><h3>Valid Sender List</h3></div>
            <div class="col-md-auto ms-lg-auto">
                <div class="form-actions">
                    <input type=button value='Delete' onClick='DeleteSelectedSender();' data-btn-enabled-class="btn-outline-danger" id='btnDeleteSender' class='disableButtons'>

                    <button class='buttons btn-outline-success' value='Add' onClick='InsertSenderRow();'><i class="fas fa-plus"></i> Add</button>
                    <input type='button' class='buttons btn-success' value='Save' onClick='SavePictureFrameConfig();'>
                </div>
            </div>
        </div>

        <div class='fppTableWrapper fppTableWrapperAsTable'>
            <div class='fppTableContents'>
                <table id='sendersTable' class='fppSelectableRowTable'>
                    <thead>
                        <tr class='tblheader'>
                            <th></th>
                            <th title='Email'>Email</th>
                            <th title='Folder'>Folder</th>
                            <th title='Note'>Note</th>
                            <th>Delete</th>
                        </tr>
                    </thead>
                    <tbody id='sendersBody' class='ui-sortable'>
                    </tbody>
                </table>
            </div>
        </div>

        <br>
<?
PrintSettingGroup('pfimapsettings', '', '', '', 'fpp-PictureFrame');
?>


        <input type='button' class='buttons btn-success' value='Check For New Images' onClick='CheckForNewImages();'>
        <input type='button' class='buttons btn-success' value='Generate Example Playlist' onClick='GeneratePlaylist();'>
    </fieldset>
</div>

<div id='emailPopup' title='Checking for new images' style="display: none">
    <textarea style='width: 99%; height: 500px;' disabled id='emailText'>
    </textarea>
    <input id='closeDialogButton' type='button' class='buttons' value='Close' onClick="$('#emailPopup').fppDialog('close');" style='display: none;'>
</div>

<datalist id='imageFolders'>
<?
foreach ($imageFolders as $dirName) {
    printf( "<option value='%s'>%s</option>\n", $dirName, $dirName);
}
?>
</datalist>
