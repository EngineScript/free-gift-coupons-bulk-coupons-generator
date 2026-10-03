---
name: "Admin Script Test Failure"
about: "Automated issue created when the admin script tests fail"
title: "Admin Script Test Failure - {{ env.FAILURE_STAGE }}"
labels: bug, testing, javascript, automated
assignees: []
---

## Admin Script Test Failure

The admin script test job failed. A setup or installation failure does not mean the script is broken.

**Failure stage:** `{{ env.FAILURE_STAGE }}`

### Details

- **Node.js Version:** {{ env.NODE_VERSION }}
- **Test Date:** {{ date | date('YYYY-MM-DD HH:mm:ss') }}
- **Workflow Run:** [View detailed logs]({{ env.WORKFLOW_URL }})
- **Run ID:** {{ env.RUN_ID }}

### What the job runs

`npm test` runs `tests/js/*.test.mjs` with the Node.js test runner. The tests load the real `assets/js/admin.js` in jsdom, against markup and settings that `tests/js/render-admin-page.php` renders from the plugin's PHP classes.

### Next Steps

1. Find the failed stage above and read that step's log.
2. If a test failed, check whether the script, the page markup, or the script settings changed.
3. Fix the code or the test, then re-run the workflow.

This issue was automatically created by the CI/CD pipeline.
