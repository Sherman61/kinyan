# Kinyan Engineering Requirements

These instructions apply to the entire repository. Treat them as acceptance criteria for every change, not optional suggestions.

## Product Priorities

Kinyan is a direct-contact vehicle marketplace. Every implementation must prioritize:

1. Security and user privacy.
2. Clear, accessible UX on mobile and desktop.
3. Complete loading, success, empty, pending, and error states.
4. Reliable rate limiting and abuse prevention.
5. Maintainable code that follows the existing PHP, MySQL, CSS, and JavaScript patterns.

Do not trade security or clarity for faster implementation.

## Design And UI

- Keep the interface quiet, practical, and optimized for repeated marketplace tasks.
- Reuse the existing visual system, spacing, colors, controls, and component patterns.
- Design mobile-first and verify common phone, tablet, laptop, and desktop widths.
- Prevent horizontal overflow, clipped text, overlapping controls, and layout shifts.
- Use stable dimensions or aspect ratios for images, cards, galleries, buttons, tables, counters, and toolbars.
- Keep admin interfaces dense but readable. Navigation must remain consistent across every admin page.
- Use familiar controls: icons for common actions, toggles for binary settings, select menus for option sets, and clear buttons for commands.
- Every interactive control needs hover, focus, active, disabled, and submitting behavior where applicable.
- Use concise, specific labels and realistic placeholders. Tell users what format or information is expected.
- Never expose zero-based numbering to users. Display image and ordering positions starting at 1.
- Avoid nested cards, excessive decoration, oversized headings in compact tools, and one-color interfaces.
- Preserve accessibility: semantic HTML, keyboard support, visible focus, useful labels, sufficient contrast, and appropriate ARIA attributes.
- User-facing dates, prices, distances, file limits, and status labels must include clear units and formatting.

## Required Application States

Every page, data view, form, request, upload, and interactive workflow must deliberately handle all applicable states:

- Initial or idle: before the user takes action.
- Loading: data is being fetched. Use a skeleton, spinner, or progress indicator appropriate to the wait.
- Submitting or processing: disable duplicate submission and show what is happening.
- Uploading: show file count, current stage, progress percentage, successes, and failures.
- Partial loading or refreshing: keep existing content visible while fresh content loads.
- Success or populated: show the completed result and a clear next action.
- Pending: explain that moderation, processing, or approval is still in progress.
- Empty: the request succeeded but no data exists. Explain what can be done next.
- No results: filters or search matched nothing. Provide a clear way to reset or adjust them.
- Error: explain the failure in plain language and provide a recovery action.
- Partial failure: preserve successful work and identify only what failed.
- Offline: distinguish lack of connectivity from a server error.
- Access denied: explain that permission is missing without exposing private information.
- Maintenance or unavailable: explain that the service is temporarily unavailable and allow retrying.
- First-time use: give a useful starting action instead of showing an empty dashboard.

Never leave users looking at a blank region, frozen control, raw exception, generic "failed" message, or unexplained disabled button.

## Errors And Alerts

- Users must always receive a human-readable error message that explains what failed and what to do next.
- Never display stack traces, SQL errors, server paths, credentials, internal IDs, or raw exception messages to normal users.
- Unexpected failures must be recorded through the centralized application error logger.
- Error records should include a user-safe message, technical cause, page, request method, sanitized context, exception class, file, line, and stack trace when available.
- Redact passwords, password hashes, CSRF tokens, authorization headers, cookies, session values, API keys, upload temporary paths, and other secrets.
- Give users an error reference such as `ERR-123` when an error is logged so support can locate it.
- Use the existing flash, toast, confirmation modal, status page, and admin error inbox instead of browser `alert()` or `confirm()`.
- Alerts must be dismissible and should not cover controls or content.
- Expected validation failures should not be logged as server errors unless they indicate abuse or a broken application path.
- Preserve submitted values after recoverable form errors, except secrets such as passwords.

## Security

- Treat every request value, uploaded file, API response, database value, and URL parameter as untrusted.
- Escape all output with the repository helper appropriate to its HTML, attribute, URL, JavaScript, or JSON context.
- Use prepared statements for every value supplied to SQL. Never concatenate untrusted input into queries.
- Validate allowed table names, column names, sort orders, statuses, and other SQL identifiers against explicit allowlists.
- Require CSRF protection for every state-changing browser request.
- Require authentication and ownership checks before reading or changing private user resources.
- Require explicit admin authorization for every admin route and action; hiding navigation is not authorization.
- Do not reveal whether private users, files, or records exist to unauthorized callers.
- Store passwords only with PHP password hashing APIs. Never log or return passwords.
- Keep secrets in environment configuration. Never commit credentials, tokens, private keys, or production secrets.
- Use secure session cookies with `HttpOnly`, `SameSite`, and `Secure` under HTTPS. Regenerate the session ID after login.
- Validate redirect destinations to prevent open redirects.
- Add security headers when changing HTTP or Apache configuration, including protections against MIME sniffing, framing, and unsafe referrers.
- Avoid destructive GET requests. Mutations must use an authenticated, CSRF-protected method.
- Record security-relevant failures without storing unnecessary personal information.

## Rate Limiting And Abuse Prevention

- Apply server-side rate limits to login, registration, password-related actions, VIN lookups, uploads, posting, editing, reports, contact tracking, sharing, and other abuse-prone endpoints.
- Rate limits must be enforced on the server. Client-side delays are not security controls.
- Choose limits based on risk and expected normal use; do not use one global threshold for every action.
- Scope limits with an appropriate combination of action, authenticated user ID, IP address, and target resource.
- Return a human-readable retry message and use HTTP `429` for rate-limited API requests.
- Do not trust proxy headers for client identity unless the proxy is explicitly configured and trusted.
- Rate-limit failed attempts as well as successful requests where abuse remains possible.
- Clean up expired rate-limit records safely so the table cannot grow without bound.
- Avoid limits that cause one user behind shared internet access to block every other user unnecessarily.

## File And Image Uploads

- Enforce upload count and size limits in both the UI and server. Car listings allow at most 10 images.
- Validate upload errors, MIME type, file signature, dimensions, pixel count, extension, and actual decodability.
- Never trust the original filename or client-provided MIME type.
- Generate random server-side filenames and keep executable content out of upload paths.
- Re-encode accepted images with ImageMagick, remove metadata, resize to a practical maximum, and store WebP at an appropriate quality.
- Set ImageMagick memory, map, pixel, and processing limits to reduce denial-of-service risk.
- Preserve successfully uploaded files when another file fails. Report passed, failed, and skipped counts clearly.
- PDF reports must be size-limited, signature-checked, MIME-checked, stored outside the public web root, and downloaded through an authorized PHP endpoint.
- Uploaded content must never be interpreted as PHP, HTML, JavaScript, SVG, shell code, or an Apache configuration file.
- Delete orphaned files when a database operation is rolled back, but do not delete previously saved user files after an unrelated upload failure.

## Forms And Marketplace UX

- Required fields must be visibly identified and validated on both client and server.
- Placeholders are examples, not substitutes for labels.
- Explain specialized fields such as lease takeover amounts, mileage allowance, accident history, title status, VIN, and travel distance.
- VIN results may prefill fields, but users must be able to review and edit the values.
- Do not present NHTSA decode data as an accident, ownership, odometer, title, or sale-history report.
- Distinguish new vehicles, used vehicles, regular sales, and lease takeovers consistently in posting, filtering, cards, and details.
- Confirmation dialogs must name the item and consequence, especially for deletion or irreversible actions.
- Copy, share, call, text, and email actions need success and failure feedback.
- Galleries must support ordered images, captions or titles, thumbnails, keyboard-friendly navigation, and enlargement.

## Data And Database Changes

- Add schema changes to `database/schema.sql` and create an idempotent migration in `database/migrations/`.
- Use transactions when an operation changes multiple related records or combines files and database writes.
- Roll back safely and clean up only artifacts created by the failed transaction.
- Add indexes for frequently filtered, joined, or sorted columns when justified by the query path.
- Preserve backward compatibility during deployment whenever old code and new schema may briefly coexist.
- Do not silently discard existing user data during migrations or edits.

## Testing And Verification

- Run PHP syntax checks on every changed PHP file.
- Run JavaScript syntax or project checks for changed JavaScript.
- Test the real workflow, not only helper functions or successful paths.
- Verify validation, authorization, CSRF, rate limits, upload failures, partial failures, empty states, pending states, and server errors as applicable.
- Test desktop and mobile layouts for every user-facing UI change.
- Check for console errors, missing assets, overflow, clipped controls, broken focus order, and inaccessible interactions.
- Test with realistic long titles, emails, prices, error text, zero records, many records, and the maximum number of uploads.
- Never claim a test passed if it was not run. State unavailable tooling or untested risk clearly.
- Remove temporary QA files, routes, records, sessions, and processes before finishing.

## Code And Repository Practices

- Follow existing repository patterns before introducing a new abstraction or dependency.
- Keep changes scoped to the requested behavior and avoid unrelated formatting or asset churn.
- Use clear names and short comments only where the reason is not obvious from the code.
- Do not commit generated secrets, local environment files, logs, uploads, database dumps, or temporary screenshots.
- Do not overwrite or revert unrelated user changes in a dirty worktree.
- Update `checklist.txt` when work completes or changes the application-state checklist.
- Keep user-facing copy direct, grammatical, and understandable without technical knowledge.
- Before finishing, review the diff for exposed secrets, raw errors, missing states, missing authorization, and accidental destructive behavior.

## Definition Of Done

A change is complete only when:

- The requested workflow works end to end.
- Security and authorization checks are server-enforced.
- Appropriate rate limits are present.
- Loading, submitting, pending, success, empty, no-results, partial-failure, offline, access-denied, and error states were considered and implemented where applicable.
- Human-readable messages and recovery actions are present.
- Mobile and desktop layouts are usable without overflow or overlap.
- Relevant syntax, functional, and visual checks pass.
- Migrations and documentation are included when required.
- No secrets, temporary artifacts, raw exceptions, or unrelated changes are included.
