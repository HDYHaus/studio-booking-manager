# Contributing to Studio Booking Manager

Thank you for contributing to Studio Booking Manager.

## Development Workflow

1. Create a GitHub Issue.
2. Agree on the architecture.
3. Update or create the relevant document in `docs/architecture/`.
4. Create a feature branch from `dev`.
5. Implement the feature.
6. Test in LocalWP.
7. Run Plugin Check.
8. Open a Pull Request into `dev`.
9. Review and merge.
10. Merge `dev` into `main` only for stable releases.

## Branch Strategy

- `main` is for stable releases.
- `dev` is the integration branch.
- Feature branches are created from `dev`.

Example feature branches:

- `feature-issue-pass`
- `feature-bookings`
- `feature-reports`

## Coding Standards

- Follow the existing architecture.
- Use namespaces.
- Use services for business logic.
- Use repositories for database access.
- Use business language in the user interface.
- Use developer language internally where appropriate.
- Follow WordPress Coding Standards.

## Security Requirements

Every change must include:

- Capability checks
- Nonce verification
- Input sanitization
- Output escaping

## Testing

Every feature should be tested with:

- LocalWP
- Manual testing
- Plugin Check

## Pull Requests

Every Pull Request should include:

- Summary
- What changed
- How it was tested
- Known limitations

## Commit Messages

Use clear commit messages.

Examples:

- `feat: add Passes foundation`
- `fix: resolve QR check-in validation`
- `docs: update architecture`
- `refactor: simplify access service`

## Project Principle

Business language always takes priority over developer language in the user interface.