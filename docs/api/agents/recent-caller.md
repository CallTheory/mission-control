# Recent Caller API

## Overview

The **Recent Caller API** returns previous caller data (fields entered into previous scripts from that number) through a TLS-protected JSON REST-ful API.

>The **Recent Caller API** works without any secondary databases.

## Features

- **Auto-Populate by ANI**: Get all instances of previous messages based on the `_acdANI` field
- **Auto-Populate by Phone field**: Get all instances of previous messages based on a designated `Phone` field in the script
- **Distinct by ANI**: Choose which fields to return and get a distinct list of previous messages based on the `_acdANI` field
- **Distinct by Phone field**: Choose which fields to return and get a distinct list of messages based on a designated `Phone` field in the script

> Since this is an API, you can use our examples or change the template to your liking.

## API Details

> **Endpoint**: `/api/agents/recent-caller/{ClientNumber}`

| Parameter(s)   | Required     | Type      | Details                                       | Description                                                     |
|----------------|--------------|-----------|-----------------------------------------------|-----------------------------------------------------------------|
| `ClientNumber` | **Required** | `Integer` | Inline in the endpoint URL                    | Typically the `_acdClientNumber` field from Intelligent Series  |
| `ANI`          | **Required** | `String`  | `POST` field, length should be **10**         | Typically the `_acdANI` field from Intelligent Series           |
| `ResultCount`  | *Optional*   | `Integer` | `POST` field, length between **1** and **10** | The number of results to return (default is `5` if not specified) |


### CURL

Some examples of usage through CURL.

| Parameter      | Assumed Example Value                                               |
|----------------|---------------------------------------------------------------------|
| `ClientNumber` | `1234`                                                              |
| `ANI`          | `5551234567`                                                        |
| `ResultCount`  | `1`                                                                 |\
| API Endpoint   | `/api/agents/recent-caller/{ClientNumber}` |
| Bearer Token   | `MISSION_CONTROL_API_TOKEN`                                         |

#### Simple

A simple CURL request that shows the usage `ANI`, `ClientNumber`, and bearer-token authorization.
The last 5 calls from the `ANI` are returned and can be selected via a picklist.

```curl
curl --location --request POST 'https://mc.yourdomain.tld/api/agents/recent-caller/1234' \
--header 'Authorization: Bearer MISSION_CONTROL_API_TOKEN' \
--form 'ANI="5551234567"' \
```

#### Latest Call

A CURL request that returns only the most recent call from the `ANI` on the specified account.

```curl
curl --location --request POST 'https://mc.yourdomain.tld/api/agents/recent-caller/1234' \
--header 'Authorization: Bearer MISSION_CONTROL_API_TOKEN' \
--form 'ANI="5551234567"' \
--form 'ResultCount="1"'
```

## Download

You can download the script here:

[Template_Recent_Caller_Lookup_MsgScript_v2.iif](../assets/files/Template_Recent_Caller_Lookup_MsgScript_v2.iif){: .md-button .md-button--blue}

