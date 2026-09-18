# Preg Match

Returns the regular expression match of a string.

## Request

**Url**: `/api/utilities/preg-match`

**Method**: `GET`,`POST`

**Query Params**/**Form Params**:

- `string` (required, string)
- `pattern` (required, string)
- Authentication, if required — see the Authentication section below

### Headers

| Header          | Value              | Notes                            |
|-----------------|--------------------|----------------------------------|
| `Content-Type`  | `application/json` | Required                         |
| `Authorization` | `Bearer <api_key>` | Only when API tokens are required |
| `Accept`        | `application/json` | Only when API tokens are required |

### Authentication

Depending on your system configuration, different methods of authentication may be required:

- **None**
- **API Token** (bearer)
- **IP Whitelisting**


If API tokens are required, you can generate one by visiting `/user/api-tokens` in your Mission Control instance.

> Click the user icon in the top right corner, then click `API Tokens`.

#### None

There is no authentication required. Useful when network security is handled by a firewall or other means.

#### API Token

Send the token in the `Authorization` header:

```
Authorization: Bearer <api_key>
Accept: application/json
```

The `Accept` header is required so that errors come back as JSON rather than HTML.

#### IP Whitelisting

Configured on the server, IP whitelisting requires you to make the request from an allowed
IP address.

## Response

### Success

- `GET /api/utilities/preg-match?string=Call 555-1212 or 1-800-555-1212&pattern=/\(?  (\d{3})?  \)?  (?(1)  [\-\s] ) \d{3}-\d{4}/x`

A successful response will return a `200 OK` with a JSON-encoded array of matches.

- **HTTP Status Code**: 200

```json
[
    "555-1212",
    "800-555-1212"
]
```

### Failure

- `GET /api/utilities/preg-match`

An un-successful response will return a `400 Bad Request` with a description of the error.

- **HTTP Status Code**: 400

```json
{
    "message": "Missing `string` parameter (GET or POST)"
}
```
