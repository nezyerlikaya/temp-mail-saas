# RC3 Certification

## Certification Process

1. Run `system:rc3-certification`.
2. Review security, staging, provider, domain, first-real-mail, load, monitoring, go-live, operational, and system foundation sections.
3. Resolve every blocker before launch sign-off.
4. Record accepted warnings with an owner and follow-up date.

## Blocker Review Process

- Security blockers belong to the security owner.
- Infrastructure and queue blockers belong to the platform owner.
- Provider and domain blockers belong to mail operations.
- Billing blockers belong to billing operations.
- Monitoring and incident blockers belong to operations.

## Launch Review Process

- Confirm installer lock, auth, RBAC, localization, media, content, inbox, mail pipeline, domain pool, provider activation, billing, API, operations, monitoring, and automation foundations.
- Confirm readiness systems agree before certification.
- Do not perform production deployment from the certification command.

## Operational Review Process

- Confirm queue and scheduler readiness.
- Confirm backup and rollback readiness.
- Confirm monitoring and incident readiness.
- Review open critical incidents before sign-off.

## Sign-Off Guidance

- `CERTIFIED` means no blockers or warnings remain.
- `WARNING` requires explicit operator review.
- `BLOCKED` prevents production launch.
- Keep the certification report free of secrets, provider credentials, and raw payloads.
