# Embed & Share architecture

Phase 6 adds the authenticated configuration surface for each bot's public widget. Each bot owns exactly one tenant-scoped `widgets` record with an opaque ULID, an enabled flag, an approved accent color, a launcher position, and a welcome message. Phase 7 activates those stable links through the versioned loader, hosted chat, demo page, and public chat API documented in `PUBLIC_WIDGET.md`.

The embed snippet and reserved hosted-chat URL contain only the widget ULID. They never contain the user ID, bot database ID, OpenAI configuration, or tenant metadata. Bot ownership is resolved from the authenticated subscriber for every configuration read and write.

Subscribers configure an exact HTTP/HTTPS origin allowlist on this page. The hosted page and NeuralDesk demo use the application's own origin and remain available without adding it manually. A customer-domain frame request is refused unless its browser origin matches an enabled widget's allowlist.

Widget appearance values are restricted to the server-controlled palette in `config/neuraldesk.php` and the `bottom_left` or `bottom_right` positions. Existing bots receive their widget record idempotently when an authorized owner first opens Embed & Share. Newly created bots receive settings and a widget in the existing bot-creation transaction.
