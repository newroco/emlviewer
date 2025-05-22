# Eml Viewer
This is a nextcloud app which provides an email preview functionality. 
It creates preview for .eml files and displays it as a modal. It also provides an easy PDF export function.

# :zap: Requirements
This app requires php version 8.1 or greater and mbstring extension
Tidy is optional, makes html email look better if installed.
<pre>
sudo apt-get update
sudo apt-get install php-tidy
sudo apt-get install php-mbstring
sudo service apache2 reload
</pre>

# :rocket: Installation

To install it simply copy and paste the application into the apps directory of your nextcloud server instance. 
After that log into nextcloud and if you have admin rights navigate to your account > Apps and press the search button at the top.
Look for "Eml Viewer" and press enable. 
When done press home button and now you should see a preview each time you click to open a .eml file.

# :rocket: Updating

After replacing the app folder, please make sure to install any new dependencies and disable then enable the app once more.
