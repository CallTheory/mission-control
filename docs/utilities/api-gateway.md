# API Gateway

A collection of 1st-party utility APIs and 3rd-party BYOK API workflows (Bring Your Own Keys) that integrate into Intelligent Series scripting for use with any number of clients. 


All endpoints below live under `/api` and are available only while the **API Gateway**
utility is enabled. Authentication is controlled at
[System → API Gateway](../system/api-gateway.md) — an API token, an IP whitelist, both, or
neither.

## Mission Control APIs

- [Recent Caller](../api/agents/recent-caller.md) — `POST /api/agents/recent-caller/{clientNumber}`
- [Inbound Email](../api/agents/inbound-email.md) — view and forward inbound email
- **Me** — `GET /api/me` returns the authenticated user. Requires an API token; useful for
  verifying that a token works.
- **MCP** — `POST /api/mcp/protocol`, the [MCP Server](mcp-server.md) endpoint

## String Case

- [Camel Case](../api/utilities/camel-case.md)
- [Kebab Case](../api/utilities/kebab-case.md)
- [Studly Case](../api/utilities/studly-case.md)
- [Snake Case](../api/utilities/snake-case.md)
- [Title Case](../api/utilities/title-case.md)
- [APA Title Case](../api/utilities/apa-title-case.md)

## String Encoding

- [Base64 Encode](../api/utilities/base64-encode.md)
- [Base64 Decode](../api/utilities/base64-decode.md)
- [Transliterate ASCII](../api/utilities/transliterate-ascii.md)

## String Utilities

- [Text Between](../api/utilities/text-between.md)
- [Preg Match](../api/utilities/preg-match.md)

## Comparisons

- [Is JSON](../api/utilities/is-json.md)
- [Is URL](../api/utilities/is-url.md)
