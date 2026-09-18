# Inbound Email

The *Inbound Email* feature allows you to receive emails from your customers and have them automatically processed by Mission Control.

## Access

Inbound Email requires the `utility.inbound_email` capability, and must be enabled both as
a system utility and for the team. See [Permissions](../system/permissions.md).

## Setup

We leverage SendGrid to receive emails. You will need to create a SendGrid account and set
up an Inbound Parse webhook. See [Integrations](../system/integrations.md#sendgrid) for the
values to enter.

## SendGrid Webhook

You will be provided details to enter into Sendgrid for your webhook and API key. This will allow Mission Control to receive emails and process them.

## Creating Rules

A rule consists of:

- a nickname to refer to the rule
- an arbitrary category/categorization for the script to use
- the account to integrate with
- the status of the rule (enabled or disabled)
- a list of the rule conditions

Support rule conditions include `To`, `From`, `Subject`, and `Body` that can be combined with `Exact Match`, `Contains`, `Starts With`, and `Ends With`.

You can also specify multiple conditions for a given rule: all conditions must be met to trigger the rule.

## Retention

Received emails are kept for 30 days by default (`INBOUND_EMAIL_DAYS_TO_KEEP`) and are
cleared hourly.

## API

Agents can view and forward a received email from within a script. See the
[Inbound Email API](../api/agents/inbound-email.md).
