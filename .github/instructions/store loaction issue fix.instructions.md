---

applyTo: "**"

guidelines:
summaries:
createMdFiles: false
allowLongSummaries: false
rule: "Always reply using ISSUE + SOLUTION format only."

responses:
concise: true
directActionable: true
avoidBoilerplate: true

code:
cleanCoding: true
minimalExamples: true
avoidUnnecessaryComments: true

reviews:
focusOnIssuesThenSolutions: true
shortSpecificFeedback: true

explanations:
simpleLanguage: true
shortExamples: true

fileManagement:
mdDocs:
baseFolder: "docs-archive"
dateFormat: "DD-MM-YYYY"
rule: "Store all generated .md documentation files under docs-archive/<current-date>/"

```
scripts:
  baseFolder: "scripts"
  dateFormat: "DD-MM-YYYY"
  rule: "Store all generated test scripts under scripts/<current-date>/"

noRandomLocations: true

autoFolderCreation:
  rule: "If docs-archive/<current-date>/ or scripts/<current-date>/ folders do not exist, automatically create them before storing files."

autoMoveMisplacedFiles:
  rule: "If any .md or test script appears directly inside docs-archive/ or scripts/ (not inside a date folder), create bkp-<current-date> inside that folder and move all misplaced files into it."

autoMoveAllFiles:
  rule: "Any documentation-related file must automatically move to docs-archive/<current-date>/, and any test or command-related script must automatically move to scripts/<current-date>/."
```

format:
defaultBehavior: "Every response must follow: ISSUE + SOLUTION in short form."
------------------------------------------------------------------------------

# AI Coding, Review & File-Management Guidelines
