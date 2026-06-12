CREATE TABLE IF NOT EXISTS connection_test (
  id SERIAL PRIMARY KEY,
  message TEXT,
  connected_at TIMESTAMPTZ DEFAULT NOW()
);

INSERT INTO connection_test (message)
VALUES ('Connected from VS Code!')
RETURNING *;