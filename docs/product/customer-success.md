# Customer Success & Support Intelligence

STEP57 introduces a first-party support operations foundation without a ticket portal UI, live chat, or external helpdesk integration.

## Support Workflow

- Support requests may belong to a user, an organization, both, or neither.
- Category and priority values are enum-backed.
- New requests begin in the open state.
- Operators may move requests through in-progress, waiting, resolved, and closed states.

## Request Lifecycle

- The first non-open transition records `first_response_at`.
- Resolved or closed requests record `resolved_at`.
- Support analytics computes aggregate response and resolution time metrics.
- Operations events record safe classification fields only.

## Response Metrics

- Average first response time.
- Average resolution time.
- Open request count.
- Category distribution.
- Priority distribution.
- Status distribution.

## Customer Health Model

Customer health combines aggregate support activity, feedback issue counts, billing issue counts, abuse issue counts, and operational risk counts. It returns:

- `healthy`
- `attention`
- `risk`

## Privacy Protections

- Ticket subjects and messages are stripped of HTML.
- Email-like values are redacted.
- Sensitive metadata keys such as bodies, payloads, request values, mailbox values, tokens, secrets, passwords, IP values, and email fields are removed.
- Reports and commands expose aggregate counts only.
- Ticket text is never written to operations events.
