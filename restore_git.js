const fs = require('fs');
const zlib = require('zlib');
const path = require('path');
const root = process.cwd();
const gitdir = path.join(root, '.git');
function readFile(rel) {
  return fs.readFileSync(path.join(gitdir, rel));
}
let HEAD = readFile('HEAD').toString('utf8').trim();
let sha = HEAD.startsWith('ref: ') ? readFile(HEAD.slice(5).trim()).toString('utf8').trim() : HEAD;
console.log('Restoring commit', sha);
function catFile(hash) {
  const obj = readFile(path.join('objects', hash.slice(0, 2), hash.slice(2)));
  const data = zlib.inflateSync(obj);
  const nul = data.indexOf(0);
  const header = data.slice(0, nul).toString('utf8');
  const [type] = header.split(' ');
  return { type, body: data.slice(nul + 1) };
}
function parseTree(body) {
  let i = 0;
  const entries = [];
  while (i < body.length) {
    let j = i;
    while (body[j] !== 32) j++;
    const mode = body.slice(i, j).toString('utf8');
    i = j + 1;
    j = i;
    while (body[j] !== 0) j++;
    const name = body.slice(i, j).toString('utf8');
    const sha = body.slice(j + 1, j + 21).toString('hex');
    entries.push({ mode, name, sha });
    i = j + 21;
  }
  return entries;
}
function restoreTree(treeHash, dest) {
  const { type, body } = catFile(treeHash);
  if (type !== 'tree') throw new Error('not a tree ' + treeHash);
  for (const entry of parseTree(body)) {
    const target = path.join(dest, entry.name);
    if (entry.mode.startsWith('04')) {
      if (!fs.existsSync(target)) fs.mkdirSync(target, { recursive: true });
      restoreTree(entry.sha, target);
    } else if (entry.mode.startsWith('10') || entry.mode === '100644' || entry.mode === '100755' || entry.mode === '120000') {
      const { type: tt, body: blob } = catFile(entry.sha);
      if (tt !== 'blob') throw new Error('expected blob ' + entry.sha);
      fs.mkdirSync(path.dirname(target), { recursive: true });
      fs.writeFileSync(target, blob);
      if (entry.mode === '100755') fs.chmodSync(target, 0o755);
    } else {
      console.warn('unknown mode', entry.mode, entry.name);
    }
  }
}
const commit = catFile(sha);
if (commit.type !== 'commit') throw new Error('not commit');
const body = commit.body.toString('utf8');
const treeLine = body.split('\n').find((l) => l.startsWith('tree '));
const treeHash = treeLine.split(' ')[1].trim();
console.log('tree', treeHash);
restoreTree(treeHash, root);
console.log('restore complete');
