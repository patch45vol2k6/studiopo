Scriptwise Writer — XAMPP setup
================================

Files in this folder:
  writer.html   the editor page (open this in a browser)
  writer.php    the POST-only backend that saves/loads the document
  data/         created automatically on first save, holds document.json

1. Install XAMPP if you don't have it: https://www.apachefriends.org
2. Start the Apache module from the XAMPP control panel (you don't need
   MySQL for this — writer.php stores data in a JSON file, not a database).
3. Copy this whole "writer" folder into XAMPP's web root:
     Windows:  C:\xampp\htdocs\writer
     macOS:    /Applications/XAMPP/htdocs/writer
     Linux:    /opt/lampp/htdocs/writer
4. Open this URL in a browser:
     http://localhost/writer/writer.html
   (Not writer.php directly, and not file:// — it must be loaded through
   Apache so the POST requests work.)
5. Start typing. It autosaves about 1.5 seconds after you stop typing, and
   the "Save" button saves immediately. The status text next to the title
   shows "Saving…" then "Saved HH:MM".

How it works
------------
- writer.html never writes to the server directly. It only sends POST
  requests to writer.php with an "action" field: "load" on page open,
  "save" whenever you type.
- writer.php only accepts POST (it returns a 405 error for GET, as you
  asked). It stores one document as JSON in data/document.json.
- This is a single shared document, not per-user accounts — anyone who
  opens writer.html on this server sees and edits the same file. That
  matches a simple local/demo setup. For multiple students with separate
  documents, each would need their own document id and some form of login,
  which this doesn't include yet.

If saving fails
----------------
If PHP can't create the data/ folder or write document.json, check that
the writer folder is writable by the web server user. On Windows/XAMPP
this usually works by default; on Linux you may need:
  chmod -R 775 writer/data
