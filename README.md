# local_cwk_add_files_mgr
Local plugin to manage Coursework's additional files upload and deletion.

# Overview
There are two capabilities added for this plugin: local/cwk_add_files_mgr:addfiles and local/cwk_add_files_mgr:deletefiles. 
Users need addfiles but can also have delete files capability to delete files. If a user has one of the two capabilities in any context, they will see a link to the dashboard on their profile page under the Coursework Management section.
The dashboard is a tabbed interface. With addfiles capability the user will see the 'Add files' tab and if also has deletefiles capability, will also see the Delete files tab.

# Settings
There is one setting where site admin can set a file upload limit.

# Add files tab
Users can upload individual files or a zip file (need to unzip in the UI). These files naming convention should be as follows:
[NAME_OF_GROUP_CWK_RESTRICTED_TO]-[Name_of_the_file.pdf] 
The hyphen (-) is used to explode the uploaded filename into group name and displayed filename.
Once an uploaded file is validated where the group is part of the restriction set for a coursework module, it will store the filename in the context of the coursework module, the cwk component and introattachment filearea.
This will result in the file being added to the additional files area for that cwk module.

Files that are not validated will return an error notification to the UI informing the user that No coursework instance exist for that file or if the same file is uploaded then it will give that message.

If a user is a site admin they will also see a button to the settings page.

# Delete files tab
This tab has two sub tabs, 'Per Upload' and 'Per Course'.
Per Upload - You can select from a dropdown list of upload instances to view and/or delete files that were uploaded in that upload. The list is constructed with the name of the uploader and the time the files were uploaded.
Per Course - You can select from a list of courses that the logged in user has the deletefiles capability in (if set at site level this will be all courses) but only courses that has a coursework module.

You can delete a single file on any of the sub tabs but also alss files in that course or that upload.

Delvon Forrester, 20 September 2023
