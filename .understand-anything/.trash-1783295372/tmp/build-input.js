const fs = require('fs');
const dir = 'C:/fotobooth/photobooth/.understand-anything/tmp/';
const nodes = JSON.parse(fs.readFileSync(dir + 'tour-filenodes.json', 'utf8'));
const layers = JSON.parse(fs.readFileSync(dir + 'tour-layers.json', 'utf8'));
const edges = JSON.parse(fs.readFileSync(dir + 'tour-alledges.json', 'utf8'));
fs.writeFileSync(dir + 'ua-tour-input.json', JSON.stringify({ nodes, edges, layers }, null, 2));
console.log('input built:', nodes.length, 'nodes', edges.length, 'edges', layers.length, 'layers');
