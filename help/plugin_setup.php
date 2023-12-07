<h2>FPP Picture Frame Setup</h2>

<b>Setup Instructions:</b><br>
<li>Create Pixel Overlay Model</li>
<li>(Optional) Create picture folders</li>
<li>(Optional) List valid senders</li>
<li>(Optional) Configure IMAP server info</li>
<li>Create Slideshow-Example Playlist or create your own</li>
<li>Schedule the playlist</li>
<br>

<br>
<h3>Picture Overlay Model</h3>

The Picture Frame plugin displays images on a Pixel Overlay Model using FPP's Playlist.
Normally this Pixel Overlay Model would be the HDMI (framebuffer) output on a FPP
system.  Normally, you will need to create a FrameBuffer Pixel Overlay Model named 'fb0'
for the first HDMI output or 'fb1' for the second HDMI output.  Set the Width and Height
of your monitor or display device, and the Pixel Size to 1.  The example playlist which
the plugin can generate will use the 'fb0' Pixel Overlay Model by default, but this can
be changed by editing the Image playlist entries in the playlist and selecting the new
Pixel Overlay Model name from the list.<br>
<br>

<br>
<h3>Picture Folders</h3>

Pictures may be stored in FPP's default Image folder or you may sort them into subfolders.  To
create a subfolder, use the '+ Add' button and give the subfolder a name.  Click the Save
button and the subfolder will be created under the Image folder.  If you want to delete an
<b>empty</b> subfolder, select the subfolder from the list and hit the Delete button.  The
subfolder will be automatically deleted in FPP, you do not need to hit the Save button.<br>
<br>
Subfolders can not be renamed, you will need to delete and recreate a subfolder to rename it.<br>
<br>
In the FPP File Manager Images tab, you will see images in subfolders listed with the
subfolder name followed by a slash and then the image name, for example "new/mom_and_dad.jpg".<br>
<br>
Images that are downloaded via email will be datestamped to eliminate filename conflicts.<br>
<br>
You may copy new images onto the system using FPP's CIFS/Samba server by browsing to
//<?php echo $_SERVER['HTTP_HOST'] ?>/fpp/images in Windows File Explorer or by having
the plugin auto-download new images from emails received using the IMAP server credentails below.<br>
<br>

<br>
<h3>Valid Sender List</h3>

When downloading images from an IMAP server, you can filter the incoming emails to block unknown
senders and to put images from different senders into different subfolders.  If the valid sender
list is empty, images received via email will be <b>unfiltered</b> allowing from all sender
addresses and will be downloaded to FPP's main images folder.<br>
<br>
The email address column is used to match the email sender, but does not have to be a full email
address.  You may use partail addresses such as the ones below:<br>
@domain = match all emails from domain<br>
domain = match all emails from domain<br>
user@domain = match emails only from user@domain<br>
<br>
For each valid sender, you may leave the Folder column blank to save the images in the default
image folder or you may select a subfolder if you want to keep some images separate.  This may
be used with features like FPP's Branch Playlist option to only display images from a certain
folder every "Nth" loop through the playlist.<br>
<br>

<br>
<h3>IMAP Mail Server Settings</h3>

Once you fill in the hostname of the IMAP server, other fields will appear to enter the credentials
and folder/mailbox name to retrieve images from.  If you use the default 'Inbox' mailbox, all
incoming emails will be downloaded.  You may also use a different mailbox and manually move
images into the secondary mailbox to allow you to manually filter what images are downloaded.<br>
<br>
By default, emails containing downloaded images will be automatically deleted from the mail server.
You may choose to keep them on the server by unchecking the 'Delete After Downloading' checkbox.<br>
<br>
<br>
The 'Check For New Images' button will run the included CheckForNewPictureFrameImages.sh script
to fetch images from the mail server.  The 'Generate Example Playlist' will create a 'Slideshow-Example'
playlist containing entries to play a random image from each of the configured subfolders or
the main folder if no subfolders have been defined.  The playlist will contain a 60 second pause
between each image.<br>
<br>

<br>
<b>Troubleshooting:</b>
If your images to not display correctly on the monitor, you may need to force the HDMI resolution
using FPP's Settings page under the System tab.  Some resolutions have more than one option in the
list, so try another if the first does not work.<br>

