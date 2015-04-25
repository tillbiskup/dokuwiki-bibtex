CREATE TABLE bibtex (key PRIMARY KEY, entry);
CREATE TABLE strings (string PRIMARY KEY, entry);
CREATE UNIQUE INDEX idx_key ON bibtex(key);
CREATE UNIQUE INDEX idx_string ON strings(string);

