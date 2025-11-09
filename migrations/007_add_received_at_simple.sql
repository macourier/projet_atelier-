-- Add received_at column to tickets as a safe, non-destructive fallback
ALTER TABLE tickets ADD COLUMN received_at DATETIME DEFAULT CURRENT_TIMESTAMP;
