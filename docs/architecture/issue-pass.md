# Contributing to Studio Booking Manager

Thank you for contributing to Studio Booking Manager.

This document describes the development workflow, coding standards, and review process used for this project.

---

# Development Workflow

Every feature follows the same process.

1. Create a GitHub Issue.
2. Discuss the feature and agree on the architecture.
3. Update or create the relevant document in `docs/architecture/`.
4. Create a feature branch from `dev`.
5. Implement the feature.
6. Test in LocalWP.
7. Run Plugin Check.
8. Open a Pull Request into `dev`.
9. Review and merge.
10. Merge `dev` into `main` only for stable releases.

---

# Branch Strategy

## main

Contains stable releases only.

## dev

Contains completed features waiting for the next release.

## feature branches

Every feature is developed in its own branch.

Examples:

- feature-issue-pass
- feature-bookings
- feature-reports

Feature branches should always be created from `dev`.

---

# Architecture First

Before writing code:

- Understand the business problem.
- Document the solution.
- Agree on the architecture.
- Then implement.

Architecture documents belong in:

```
docs/architecture/
```

---

# Coding Standards

Follow existing project conventions.

- Use namespaces.
- Reuse existing architecture.
- Prefer business language in the user interface.
- Prefer developer language internally where appropriate.
- Do not duplicate logic.
- Keep modules independent where possible.
- Follow WordPress Coding Standards.

---

# Security

Every change should include:

- Capability checks
- Nonce verification
- Input sanitization
- Output escaping

---

# Database

- Prefer additive database upgrades.
- Never destroy existing user data.
- Existing records should remain compatible after upgrades.

---

# Testing

Every feature should be tested using:

- LocalWP
- Manual testing
- Plugin Check

A feature is not complete until Plugin Check has been reviewed.

---

# Pull Requests

Every Pull Request should include:

- Summary
- What changed
- How it was tested
- Any known limitations

---

# Commit Messages

Use clear commit messages.

Examples:

- feat: add Passes foundation
- fix: resolve QR check-in validation
- docs: update architecture
- refactor: simplify access service

---

# AI Development Workflow

AI coding assistants should follow this process:

1. Read the relevant architecture documentation.
2. Review the existing implementation.
3. Follow the current project architecture.
4. Make the smallest reasonable change.
5. Avoid introducing new architectural patterns unless requested.
6. Validate with Plugin Check before completion.
7. Report any assumptions made during implementation.

---

# Project Principles

Studio Booking Manager is designed around business concepts.

Examples include:

- People
- Locations
- Passes
- Access
- Visits
- Operations

The user interface should use terminology that studio owners, libraries, makerspaces, and coworking spaces naturally understand.

Business language always takes priority over developer language.