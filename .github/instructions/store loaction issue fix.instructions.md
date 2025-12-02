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
    scripts:
      baseFolder: "scripts"
      dateFormat: "DD-MM-YYYY"
      rule: "Store all generated test scripts under scripts/<current-date>/"
    noRandomLocations: true
    autoMoveMisplacedFiles:
      rule: "Move incorrectly placed .md or script files into docs-archive/<date>/old/ or scripts/<date>/old/"

format:
  defaultBehavior: "Every response must follow: ISSUE + SOLUTION in short form."
---
# AI Coding, Review & File-Management Guidelines
