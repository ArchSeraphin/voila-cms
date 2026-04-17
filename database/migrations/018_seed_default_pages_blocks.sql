INSERT INTO static_pages_blocks (page_slug, block_key, content) VALUES
  ('home',    'hero_title',      'Bienvenue'),
  ('home',    'hero_subtitle',   'Site en cours de construction'),
  ('home',    'cta_label',       'Nous contacter'),
  ('home',    'intro_paragraph', ''),
  ('about',   'intro_title',     'À propos'),
  ('about',   'intro_text',      ''),
  ('about',   'values_block',    ''),
  ('contact', 'intro_text',      'N''hésitez pas à nous contacter.'),
  ('legal',   'editor_name',     ''),
  ('legal',   'editor_info',     ''),
  ('legal',   'hosting_info',    '')
ON DUPLICATE KEY UPDATE content = VALUES(content);
