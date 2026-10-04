# EdgeNext API Explorer

A responsive API workspace with a categorized endpoint dropdown, dynamic parameter forms, JSON/PHP previews, and real server-side request execution.

The SDK repository is a generic request client, not a complete API specification. The initial catalog contains its two firewall operations and five explicitly labeled test examples. It does not claim to list all EdgeNext APIs or know their formal required parameters.

## Run locally

Use Node 22.13 or newer. Run `npm install`, then `npm run dev`. The development URL is http://localhost:5173.

## Connect EdgeNext

Configure server environment variables (never frontend variables):

- `SDK_API_PRE`: your HTTPS API base URL including its version path.
- `SDK_APP_ID`: your application ID.
- `SDK_APP_SECRET`: your application secret, stored as a secret.

For local use place them in an ignored `.env.local` file. For the hosted Site use its runtime environment settings. No secrets are saved in browser storage or request previews. Live EdgeNext calls have not been tested without account credentials.

The hosted adapter implements the HMAC-SHA256 canonical request algorithm in `src/Request/Signer.php`, SDK 2.0.0, from upstream commit `bb4238e5be49a72ed1e98df71a8a8abc930ffa73`. It uses JSON request bodies and reproduces SDK default metadata. The upstream array-body path labels JSON as form-urlencoded; the hosted adapter deliberately uses application/json. Confirm API compatibility with your account before using production write operations.

## Use the actual PHP SDK

Sites hosting runs JavaScript Workers and cannot execute PHP. For direct use of the linked library deploy `php-bridge` on your PHP server:

1. Run `composer install` in `php-bridge`.
2. Set its web root to `php-bridge/public` and expose it over HTTPS.
3. Set `SDK_API_PRE`, `SDK_APP_ID`, `SDK_APP_SECRET`, and a strong `EDGENEXT_PHP_BRIDGE_TOKEN` on the PHP server.
4. Set `EDGENEXT_PHP_BRIDGE_URL` (the bridge HTTPS URL) and the same token as a secret in the Site's runtime environment.

When bridge settings are present they take precedence over the hosted adapter. The bridge calls `\edgenextapisdk\Sdk` using the selected method and parameters. The bridge token authenticates site-to-server calls; no browser CORS access is required. PHP requires 8.2 or newer; review SDK compatibility with your runtime. Keep the Site private when server credentials are configured.

## Populate the complete catalog

Click **Import API catalog** and choose a bundled OpenAPI 3.x JSON file. Tags become dropdown categories; each operation generates query, path, and JSON-body inputs, including required markers, enums and descriptions. Imports replace the example list for the current browser tab and are not persisted. To ship your real catalog by default, update `lib/catalog.ts`.

The importer handles local schema references, scalar/array/object fields, and ordinary query/path parameters. It explicitly rejects external or circular references, composed body schemas, non-JSON bodies, header/cookie parameters, and unsupported parameter styles instead of silently omitting them. Nested values are edited as JSON; nested schema validation is performed by EdgeNext. Parameter encoding follows PHP SDK bracket serialization. OpenAPI endpoints requiring other serialization need a dedicated adapter.

Alternatively import a JSON catalog shaped like:

```json
{"endpoints":[{"category":"Your category","name":"Your operation","method":"GET","path":"documented.endpoint","description":"From the API reference","fields":[{"name":"domain_id","type":"integer","location":"query","required":true}]}]}
```

This example demonstrates catalog structure, not an EdgeNext endpoint. A custom endpoint option is also available for paths from your documentation. Server requests are constrained to the configured API base URL and support GET, POST, PUT, PATCH and DELETE. Non-GET requests ask for confirmation because they may change your account configuration.

## Validation

Run `npx tsc --noEmit`, `npm run build`, and `node --test tests/*.test.mjs`. Signing tests compare canonical requests and signatures to independently derived reference values. Account-specific endpoints and credentials are still needed for live integration verification.
