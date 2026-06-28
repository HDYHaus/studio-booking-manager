# Issue Pass Architecture

Status: Draft

## Purpose

The Issue Pass workflow allows an administrator to issue a configured Pass to a Person.

Issuing a Pass creates an Access record for that Person.

## Core Concept

A Pass is a reusable template.

Access is the issued entitlement.

A Visit is the record of usage.

```text
Pass
  ↓
Issue Pass
  ↓
Access
  ↓
Visit