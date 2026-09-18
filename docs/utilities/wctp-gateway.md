# WCTP Gateway

> **This page has moved.** The WCTP Gateway is no longer a per-team utility. Carriers,
> phone numbers, enterprise hosts and message traffic are one installation-wide
> configuration, so the section now lives under **System → WCTP Gateway**.
>
> See **[System → WCTP Gateway](../system/wctp-gateway.md)**.

The old utility URLs redirect to their new homes:

| Old URL | New URL |
|---------|---------|
| `/utilities/wctp-gateway` | `/system/wctp/gateway` |
| `/utilities/enterprise-hosts` | `/system/wctp/enterprise-hosts` |
| `/utilities/wctp-messages` | `/system/wctp/messages` |

Access is now gated on the `wctp.manage` and `wctp.messages` capabilities, held by the
Administrator and Technical roles by default, rather than on a per-team utility toggle.
