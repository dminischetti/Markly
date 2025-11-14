# Technical Decisions

This document explains key architectural choices made in Markly.

## Why No Frameworks?

**Decision**: Use vanilla PHP and JavaScript instead of Laravel, Symfony, React, Vue, etc.

**Rationale**:
1. **Platform Knowledge** - Demonstrates understanding of underlying web technologies
2. **Zero Build Complexity** - No webpack, npm, or transpilation required
3. **Minimal Hosting Requirements** - Runs on any PHP hosting without CLI access
4. **Fast Load Times** - < 50KB total JS, no framework overhead
5. **Longevity** - No framework version churn or breaking changes

**Trade-offs**:
- Manual routing and state management
- No built-in component reactivity
- Verbose DOM manipulation

**Verdict**: Worth it for portfolio purposes and actual deployment constraints.

---

## Why Optimistic Locking?

**Decision**: Use version numbers + ETags instead of pessimistic locking.

**Rationale**:
1. **Scalability** - No database locks blocking concurrent reads
2. **User Experience** - Users see updates immediately (optimistic UI)
3. **Conflict Resolution** - Last-write-wins with conflict detection
4. **HTTP-Native** - Leverages standard ETag/If-Match headers

**Implementation**:
- Each note has a `version` column (auto-incremented on update)
- Client sends `If-Match: "v{version}"` header
- Server returns 409 Conflict if version mismatch

**Trade-offs**:
- Rare conflicts require user intervention
- Slightly more complex client-side logic

**Verdict**: Better UX than pessimistic locking for collaborative editing.

---

## Why IndexedDB + Service Worker?

**Decision**: Build offline-first using IndexedDB for storage and SW for network interception.

**Rationale**:
1. **PWA Capabilities** - Full offline functionality
2. **Large Storage** - IndexedDB supports MB of data vs. localStorage's 5-10MB
3. **Structured Queries** - Indexes and cursors for efficient lookups
4. **Modern Web Platform** - Demonstrates current best practices

**Implementation**:
- Notes cached in IndexedDB on first fetch
- Mutations queued in `outbox` store when offline
- Service Worker replays queue on reconnect

**Trade-offs**:
- More complex than REST-only architecture
- Requires HTTPS in production
- Browser compatibility (though widely supported)

**Verdict**: Essential for modern web apps, good portfolio differentiator.

---

## Why CSRF Token Pool?

**Decision**: Maintain a pool of 5 valid CSRF tokens instead of single-token validation.

**Rationale**:
1. **Concurrent Requests** - Multiple API calls in flight don't invalidate each other
2. **Race Conditions** - Avoids "token already used" errors on rapid interactions
3. **User Experience** - No false "session expired" messages

**Implementation**:
- Each token has a 30-minute TTL
- Pool pruned on each validation
- Tokens deleted after use

**Trade-offs**:
- Slightly weaker CSRF protection (5x attack window vs. 1x)
- More complex validation logic

**Verdict**: Acceptable security trade-off for better UX.

---

## Why No Composer?

**Decision**: Avoid Composer dependencies to keep deployment simple.

**Rationale**:
1. **Shared Hosting** - Many hosts don't provide shell access
2. **FTP Deployment** - Can upload via FileZilla without build step
3. **Dependency Security** - No third-party packages to audit/update
4. **Learning** - Writing own Auth, Routing, Response classes teaches fundamentals

**Implementation**:
- Custom PSR-4-style autoloader in `src/autoload.php`
- Pure PHP domain logic without abstractions

**Trade-offs**:
- Manual implementation of common patterns
- Missing convenience of libraries like PHPMailer, Guzzle, etc.

**Verdict**: Good for portfolio, not recommended for production apps with complex needs.

---

## Why Full-Text Search with LIKE Fallback?

**Decision**: Use MySQL FULLTEXT index when available, graceful LIKE fallback otherwise.

**Rationale**:
1. **Performance** - FULLTEXT indexes are optimized for text search
2. **Compatibility** - Not all MySQL versions/hosts support FULLTEXT
3. **Graceful Degradation** - App works everywhere, just slower on some hosts

**Implementation**:
```php
private function detectFulltext(): bool {
    try {
        $this->pdo->query('SELECT MATCH(...) AGAINST(...) FROM notes LIMIT 1');
        return true;
    } catch (PDOException) {
        return false;
    }
}
```

**Trade-offs**:
- LIKE search is slow on large datasets
- Different result quality between modes

**Verdict**: Good compromise for portability.

---

## Why Manual SQL Instead of ORM?

**Decision**: Write raw SQL with PDO prepared statements instead of Doctrine/Eloquent.

**Rationale**:
1. **Performance** - No ORM overhead or N+1 queries
2. **Control** - Full visibility into queries for optimization
3. **Learning** - Forces understanding of SQL, transactions, indexes
4. **Simplicity** - No migrations, schema definitions, or ORM config

**Trade-offs**:
- Verbose query building
- Manual transaction management
- No automatic relationship loading

**Verdict**: Good for small/medium apps, scales to thousands of notes easily.
