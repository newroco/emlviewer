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