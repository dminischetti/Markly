INSERT INTO users (email, pw_hash) VALUES
('admin@example.com', '$2y$12$YzxVikii6PAlBROLEKMP9O/l5TSjRzuEYKOUZbCe/QZBVEluo9E3C');

INSERT INTO notes (user_id, slug, title, content, tags, is_public, version, updated_at, created_at) VALUES
(1, 'welcome-to-markly', 'Welcome to Markly', '# Welcome to Markly\n\nThis is your new Markdown notebook. Try editing this note and hit **Ctrl/Cmd + S** to save.\n\n- Live preview with the eye icon\n- Offline editing support\n- Share individual notes publicly', 'intro,getting-started', 1, 1, NOW(), NOW()),
(1, 'weekly-planning', 'Weekly planning template', '## Weekly planning\n\n- [ ] Capture focus tasks\n- [ ] Review backlog\n- [ ] Celebrate wins\n\n> Pro tip: tag this note with `template` and duplicate as needed.', 'planning,template', 0, 1, NOW(), NOW()),
(1, 'reading-list', 'Reading list', '### Reading list\n\n1. *Designing Data-Intensive Applications*\n2. *The Pragmatic Programmer*\n3. *Inclusive Components*\n\nTag entries with status: `to-read`, `reading`, `done`.', 'lists,books', 0, 1, NOW(), NOW()),
(1, 'release-checklist', 'Release checklist', '## Release checklist\n\n- [ ] Update changelog\n- [ ] Run smoke tests\n- [ ] Publish release notes\n- [ ] Celebrate with the team 🎉', 'process,template', 0, 1, NOW(), NOW()),
(1, 'architecture-notes', 'Architecture notes', '## Architecture notes\n\n- Capture decisions with ADRs\n- Document trade-offs\n- Keep diagrams lightweight\n\n```
sequenceDiagram
  participant API
  participant DB
  API->>DB: fetch notes
```
', 'engineering,architecture', 0, 1, NOW(), NOW()),
(1, 'travel-ideas', 'Travel ideas', '### Dream destinations\n\n- Kyoto in autumn\n- Lisbon food crawl\n- Puglia road trip\n\nRemember to attach reference photos and budgeting notes.', 'personal,travel', 1, 1, NOW(), NOW()),
(1, 'meeting-notes-template', 'Meeting notes template', '## Meeting notes\n\n- **Date:**\n- **Participants:**\n- **Agenda:**\n\n### Notes\n\n### Action items\n- [ ] Owner · Due date', 'template,meetings', 0, 1, NOW(), NOW());
