# User Feedback & Product Intelligence

STEP56 introduces a first-party feedback foundation for v1.1 planning without a public portal or external feedback SaaS.

## Feedback Foundation

- `user_feedback` stores optional user ownership, classification, title, redacted message text, and sanitized metadata.
- Feedback types cover issues, feature requests, suggestions, questions, and praise.
- Feedback categories align with roadmap planning areas.
- Feedback status moves from new to reviewed, planned, or closed.

## Privacy Rules

- Feedback text is stripped of HTML.
- Email-like values in title, message, and string metadata are redacted.
- Sensitive metadata keys such as payloads, mailbox values, tokens, passwords, IP values, and email fields are removed.
- Product intelligence reports aggregate counts only.
- Operations events never contain feedback titles or messages.

## Product Intelligence

- Trend reports group feedback by category.
- Recurring issue reports group issue feedback by category.
- Feature request reports group feature request demand by category.
- Roadmap insight reports expose demand levels and risk counts without exposing user text.

## Operations Events

- `feedback_created`
- `feedback_reviewed`
- `feedback_closed`
- `roadmap_insight_generated`
