# Getting Started

The first thing you'll want to do after logging in is to create your first team.

By default, you have a personal team. This team cannot access system settings and is meant for individual users to have a personal dashboard.

In order to start inviting people, you should create a new team from the dropdown at the top.

## Create a Team

Select `Create A Team` from the menu and then enter the *Team Name*. Press **Create** to finish.

A good starting team name is `Operations` or `Agents` or similar - a place scope to your team. 

You will be redirected back to the dashboard, but you will now be logged into your new team.

From here, we can now start adding system settings and users.

## System Configuration

One of the first places you should head is the `System` link. 

The main sections are:

- General
- Data Sources
- Integrations
- Permissions
- Observability
- WCTP Gateway

Utilities with system-level configuration of their own — Cloud Faxing, Board Check, CSV
Export, MCP Server, Better Emails, API Gateway, Script Search — each get their own page
reachable from the System navigation.

### General

Here you can view the `Queue Viewer` to check on background job processing, and the
`Application Debug` for an in-depth view into what's happening in your application.

You should set the `Timezone` to your local timezone. This is typically the timezone
assigned to your Intelligent SQL server.

This page also carries the two master switches that everything else sits behind:

- **System Features** — Transcription, Screen Captures, MCP Server, WCTP Gateway
- **System Utilities** — one toggle per utility, applying to every team

Two settings that used to live here now have their own pages:

- **Board Check Configuration** is at System → Board Check. It seeds the first message for
  the board check to begin processing and generally should not be used after the initial
  setup.
- **Fax notification emails** are at System → Cloud Faxing. See
  [Cloud Faxing](utilities/cloud-faxing.md#alerts).

### Data Sources

In this section, you'll do the initial setup of your data sources:

- Intelligent Series Database Connection details
- ISWeb API Endpoint URL
- IS Service Account Username/Password

> Most features require the data sources area to be configured properly.

### Integrations

Used to connect internal and external API integrations that augment or enhance the functionality of Mission Control.

- SendGrid
- Stripe
- mFax
- RingCentral
- Twilio
- Bandwidth
- Commio
- PeoplePraise

See [Integrations](system/integrations.md) for what each one needs.

### Permissions

Permissions are capability-based and scoped to each team you create. Every new team is
seeded with six editable roles — Administrator, Manager, Supervisor, Technical, Dispatcher
and Agent — which you can rename, edit or replace.

For a user to reach a utility, three things must be true: the utility is enabled
system-wide, it is enabled for their team, and their role grants its capability.

See [Permissions](system/permissions.md) for the full capability list and for suffix
rules, which grant capabilities based on an Intelligent Series agent name such as
`JSMITH-SUP`.

There are also two types of teams:

1. Personal Teams
2. Teams

Personal teams are for the logged-in user and apply to them only. You cannot manage system
settings or access most utilities from the personal dashboard.

To access utilities and settings, switch to (one of) your team(s).


