# Pass Types Architecture

Status: Draft

## Purpose

Pass Types are configurable templates that define the memberships and passes a space can sell or issue.

A Pass Type describes what a person is entitled to receive before that entitlement is issued to them.

Examples:

- Day Pass
- Resident Membership
- Founding Membership
- Student Membership
- 10 Visit Pack
- Weekend Pass
- Podcast Studio Session
- Library Entry Pass

## Why Pass Types Exist

Studio Booking Manager should not hardcode business names such as Day Pass, Flex Pass, or Resident Membership.

Different spaces use different language and sell different kinds of access.

Pass Types allow each administrator to create the memberships and passes that match their own business.

## Relationship to Access

A Pass Type is a template.

Access is the issued pass or membership assigned to a person.

```text
Pass Type
    ↓
Access
    ↓
Visit
```

Example:

```text
Resident Membership Pass Type
    ↓
Resident Membership issued to Mary
    ↓
Mary checks in
    ↓
Visit recorded
```

## Pass Behaviour

Each Pass Type uses one of three behaviours.

### One-time

Allows one visit.

Examples:

- Day Pass
- Trial Pass
- Event Entry

### Multiple Visits

Allows a fixed number of visits.

Examples:

- 10 Visit Pack
- 20 Visit Pack
- Student 5 Visit Pack

The user interface should use the phrase **Visits Remaining**, not credits.

### Membership

Allows ongoing access while active.

Examples:

- Resident Membership
- Founding Membership
- Monthly Membership
- Annual Membership

## Core Fields

A Pass Type should include:

- Name
- Description
- Behaviour
- Visits Included
- Weekly Visit Limit
- Guest Allowance
- Booking Required
- Validity Period
- Status
- Colour
- Created Date
- Updated Date

## Business Language

Use:

- Pass Type
- One-time
- Multiple Visits
- Membership
- Visits Remaining
- Guest Allowance

Avoid:

- Access Type
- Credits
- Token
- Entity

## WooCommerce Mapping

WooCommerce products and variations should map to Pass Types.

```text
WooCommerce Product
    ↓
Pass Type
    ↓
Access Record
```

See [WooCommerce Pass Integration](woocommerce-pass-integration.md) for the product metadata and order issuance flow.

## Access Creation

When a Pass Type is issued to a person, Studio Booking Manager copies the rules from the Pass Type into the Access record.

Already issued passes should not change if the Pass Type is edited later.

## Operations

Reception staff should see active memberships and passes in business language.

Example:

```text
Mary Mojisola Job

Resident Membership
Unlimited visits
Guests allowed: 2

10 Visit Pack
Visits Remaining: 7
```

## Design Principle

Business language always beats developer language.

The plugin should use words that studio owners, reception staff, librarians, and community managers naturally understand.
