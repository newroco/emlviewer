## 2.0.1 – 2025-05-11
Updated included dependencies to fix PSR issues

## 2.0.0 – 2024-11-04
Compatbile with Nextcloud 28, 29, 30 and 31.

## 1.0.9 – 2023-02-07
Upgraded dependencies.
Fixed composer and PSR version included in vendor folder.

## 1.0.8 – 2023-09-01
Upgraded version support to NC26, NC27
- Fixed CSS styles for 25-27

## 1.0.7 – 2023-01-20
Upgrade to zbateson/mail-mime-parser otherwise fails to load mail preview on php8   

## 1.0.6 – 2023-01-20
Fixed php8 loaded vendor and restricted to NC24 and above  

## 1.0.5 – 2023-01-19
Added support for NC25,
Added support for mime 'message/rfc822',
Various bug fixes and header encoding fixes

## 1.0.4 – 2022-11-09
Better handling NC global theme, e.g dark mode

## 1.0.3 – 2022-11-09
Bug fixing.

## 1.0.2 – 2022-05-20
Added ability to access and download attachments in a shared eml file,
Upgraded version support to NC24

## 1.0.1 – 2022-03-18
Upgraded version support to NC23

## 1.0.0 – 2021-11-09
Upgraded to work on NC22

## 0.0.22 – 2020-11-18
Upgraded to work on NC21

### Added
- Added support for when sharing multiple files in folder 

## 0.0.21 – 2020-11-18
Downgraded to work on NC19

## 0.0.20 – 2020-11-18
### Added
- Added support for when sharing file 
### Changed
- Made overlay solid white to prevent eye strain

## 0.0.19 – 2020-11-18
- Upgraded to work on NC20 and fixed any deprecation issues 

## 0.0.18 – 2020-09-25
- Fixed issues with folder names containing + 

## 0.0.17 – 2020-08-20
- Adding some fonts to MPDF

## 0.0.16 – 2020-06-26
- Repository clean up
### Changed
- Improved Text-only e-mail handling

## 0.0.15 – 2020-06-25
### Removed
- Removed some more large ttf files from mpdf

## 0.0.14 – 2020-06-25
### Added
- Text-only e-mail handling

## 0.0.13 – 2020-06-24
### Added
- Added headers in PDF and on printer friendly version
- Added print button on printer friendly version
- Added ability to show and download all attachments
- Added ability to automatically replace embedded image CID urls so they show in the body of the e-mail
### Changed
- php Tidy is now optional, but without it, some emails may not export well to PDF

## 0.0.12 – 2020-05-15
### Removed
- Removed some mPDF fonts to make the release archive smaller

## 0.0.11 – 2020-05-14
### Added
- Added printer friendly button so people can print from their browser instead of
relying on our PDF version.

## 0.0.10 – 2020-05-14
### Changed
- Allowed via NC policy alteration to load external images + PDF tweaks 
  [#12](https://github.com/newroco/emlviewer/issues/12) @lucianpricop
- PDF tweaks

## 0.0.9 – 2020-05-14
### Changed
- Improved loading speed. Before the contents of the eml file were requested 
from client, then sent url encoded to server to obtain the parsed version. Now
the server access the contents directly 
    
## 0.0.8 – 2020-05-11
### Changed
- Moved mpdf tmp folder outside of vendor folder
  
## 0.0.7 – 2020-05-11
### Changed
- Replaced the tool to generate PDFs from dompdf to mpdf
- Added tidy to the mix to make sure html email is valid
  [#9](https://github.com/newroco/emlviewer/issues/9) @lucianpricop
  
## 0.0.6 – 2020-05-06
### Changed
- Improved error reporting
- Tested on NC19
- Fixed paths to fix issues
  [#8](https://github.com/newroco/emlviewer/issues/8) @lucianpricop
  [#11](https://github.com/newroco/emlviewer/issues/11) @lucianpricop
  
## 0.0.5 – 2020-01-13
### Added
- Add a CHANGELOG.md
  [#5](https://github.com/newroco/emlviewer/issues/5) @incrosnatubogdan
- Add pretty printing function
  [#6](https://github.com/newroco/emlviewer/issues/6) @incrosnatubogdan

### Changed
- Make the preview have a less transparent background
  [#1](https://github.com/newroco/emlviewer/issues/1) @incrosnatubogdan
- Button content should just be "show content" and change to suit
  [#2](https://github.com/newroco/emlviewer/issues/2) @incrosnatubogdan
- Works on nginx servers
  [#3](https://github.com/newroco/emlviewer/issues/3) @incrosnatubogdan
- Display email's date received
  [#4](https://github.com/newroco/emlviewer/issues/4) @incrosnatubogdan
- Accessing an .eml file from shared files offers to download it instead of running emlviewer
  [#7](https://github.com/newroco/emlviewer/issues/7) @incrosnatubogdan
