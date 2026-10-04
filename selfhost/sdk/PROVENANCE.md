Unmodified source from https://github.com/edgenextapisdk/edgenext-php at commit bb4238e5be49a72ed1e98df71a8a8abc930ffa73, retrieved 2026-10-04. The upstream MIT license is included.

The SDK supports a custom HttpClientInterface. This application supplies a PHP cURL transport, so the SDK's pinned Guzzle 6.3.0 dependency and Composer are not required. The transport labels the SDK's JSON payload as application/json for V5 calls, disables redirects, verifies TLS, and limits responses to 2 MB. The SDK handles request construction and signing unchanged.
