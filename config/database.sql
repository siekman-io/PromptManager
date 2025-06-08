CREATE TABLE prompts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255),
  omschrijving TEXT,
  prompt_body TEXT,
  subcategory VARCHAR(255),
  ai_platform VARCHAR(64),
  date_added DATETIME DEFAULT CURRENT_TIMESTAMP,
  last_used DATETIME DEFAULT NULL
);

