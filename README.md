# EdgeNext API Explorer

A responsive API workspace with a categorized endpoint dropdown, dynamic parameter forms, JSON/PHP previews, and real server-side request execution.

The built-in catalog comes from the official [EdgeNext API reference](https://home.console.edgenext.com/apidoc/v2/en/#overview) and its [OpenAPI definition](https://home.console.edgenext.com/apidoc/v2/en/openapi.json), retrieved on 2026-10-04. It contains all 313 documented operations across 279 paths and 33 categories. The page identifies this API as V5 despite the v2 documentation URL. The SDK section links to the same edgenextapisdk/edgenext-php repository. Required fields, choices, descriptions, and nested JSON definitions come from the official schema.

## Run locally

Use Node 22.13 or newer. Run `npm install`, then `npm run dev`. The development URL is http://localhost:5173.

## Connect EdgeNext

Configure server environment variables (never frontend variables):

- `SDK_API_PRE`: your HTTPS API server. The reference lists https://apiv4.lalcsafe.com. Built-in V5 paths include /api/v5 and resolve from the server origin. Relative custom endpoints resolve under the configured base path.
- `SDK_APP_ID`: the credential ID expected by the PHP SDK as app_id. The API overview describes console credentials as AccessKey ID and AccessKey Secret. Verify your account credential mapping with EdgeNext before live use.
- `SDK_APP_SECRET`: your application secret, stored as a secret.

For local use place them in an ignored `.env.local` file. For the hosted Site use its runtime environment settings. No secrets are saved in browser storage or request previews. Live EdgeNext V5 calls and account-specific credential mapping have not been tested without account credentials. SDK signing tests establish parity with the linked PHP SDK; they do not establish acceptance by every production endpoint.

The hosted adapter implements the HMAC-SHA256 canonical request algorithm in `src/Request/Signer.php`, SDK 2.0.0, from upstream commit `bb4238e5be49a72ed1e98df71a8a8abc930ffa73`. It uses JSON request bodies and reproduces SDK default metadata. The upstream array-body path labels JSON as form-urlencoded; the hosted adapter deliberately uses application/json. Confirm API compatibility with your account before using production write operations.

## Use the actual PHP SDK

Sites hosting runs JavaScript Workers and cannot execute PHP. For direct use of the linked library deploy `php-bridge` on your PHP server:

1. Run `composer install` in `php-bridge`.
2. Set its web root to `php-bridge/public` and expose it over HTTPS.
3. Set `SDK_API_PRE`, `SDK_APP_ID`, `SDK_APP_SECRET`, and a strong `EDGENEXT_PHP_BRIDGE_TOKEN` on the PHP server.
4. Set `EDGENEXT_PHP_BRIDGE_URL` (the bridge HTTPS URL) and the same token as a secret in the Site's runtime environment.

When bridge settings are present they take precedence over the hosted adapter. The bridge calls `\edgenextapisdk\Sdk` using the selected method and parameters. The bridge token authenticates site-to-server calls; no browser CORS access is required. PHP requires 8.2 or newer; review SDK compatibility with your runtime. Keep the Site private when server credentials are configured.

## Catalog source and imports

Click **Import API catalog** and choose a bundled OpenAPI 3.x JSON file. Tags become dropdown categories; each operation generates query, path, and JSON-body inputs, including required markers, enums and descriptions. Imports replace the built-in catalog for the current browser tab and are not persisted. The bundled official catalog is in `lib/edgenext-catalog.json`, with the original OpenAPI definition and retrieval metadata in `data/`.

The importer handles local schema references, scalar/array/object fields, and ordinary query/path parameters. It explicitly rejects external or circular references, composed body schemas, non-JSON bodies, header/cookie parameters, and unsupported parameter styles instead of silently omitting them. Nested values are edited as JSON. Built-in nested definitions are expanded from local references and shown below the inputs. The form validates declared types, required nested properties, enums, lengths, and numeric bounds. EdgeNext performs business validation. Parameter encoding follows PHP SDK bracket serialization. OpenAPI endpoints requiring other serialization need a dedicated adapter.

Alternatively import a JSON catalog shaped like:

```json
{"endpoints":[{"category":"Your category","name":"Your operation","method":"GET","path":"documented.endpoint","description":"From the API reference","fields":[{"name":"domain_id","type":"integer","location":"query","required":true}]}]}
```

This example demonstrates catalog structure, not an EdgeNext endpoint. A custom endpoint option is also available for paths from your documentation. Server requests are constrained to the configured API base URL and support GET, POST, PUT, PATCH and DELETE. Non-GET requests ask for confirmation because they may change your account configuration.

## Validation

Run `npx tsc --noEmit`, `npm run build`, and `node --test tests/*.test.mjs`. Signing tests compare canonical requests and signatures to fixtures generated by the actual upstream PHP signer. Catalog tests check that all documented operations are present and preserve schema details. Credentials are still needed for live integration verification.
