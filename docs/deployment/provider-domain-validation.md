# Provider And Domain Validation Checklist

## Mailgun

- Confirm webhook URL uses the production HTTPS URL.
- Confirm signing secret is configured.
- Confirm provider activation flag is intentional.
- Send a test inbound message.
- Verify intake, queue processing, storage, and inbox visibility.

## Postmark

- Confirm inbound webhook URL.
- Confirm webhook token configuration.
- Send a test inbound message.
- Verify normalized payload fields.

## Amazon SES

- Confirm inbound notification path.
- Confirm signing expectations.
- Verify message id, sender, and destination mapping.

## Domain MX Checklist

- Confirm MX points to the selected provider.
- Confirm provider domain verification status.
- Confirm fallback domain remains available.

## Webhook URL Checklist

- `/webhooks/mailgun`
- `/webhooks/postmark`
- `/webhooks/ses`

## Signing Secret Checklist

- Store secrets only in environment variables.
- Do not paste secrets into tickets, logs, or reports.
- Rotate secrets through deployment procedures.

## Inbound Test Checklist

- Provider accepts message.
- Webhook signature verifies.
- Intake is queued.
- Queue stores message.
- Public inbox can see the message.
- Duplicate webhook delivery does not duplicate storage.
