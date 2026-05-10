-- Performance indexes for Manage Tests queries
-- Run once in your MySQL client.

CREATE INDEX idx_questions_test_id ON questions (test_id);
CREATE INDEX idx_results_test_id ON results (test_id);
