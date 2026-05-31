# Provider Onboarding Checklist

## Mailgun

- Configure webhook URL.
- Configure signing key.
- Verify provider is enabled only after testing.
- Send a test inbound message.

## Postmark

- Configure inbound webhook URL.
- Configure webhook token.
- Verify normalized payloads reach intake.

## Amazon SES

- Configure inbound notification flow.
- Configure signing expectations.
- Verify message id and destination mapping.

## Safety

Do not store raw provider payloads in tickets. Keep provider activation flags disabled until validation passes.
