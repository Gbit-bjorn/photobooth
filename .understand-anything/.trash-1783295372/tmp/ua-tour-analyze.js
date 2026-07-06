#!/usr/bin/env node
'use strict';
const fs = require('fs');

function main() {
  const inPath = process.argv[2];
  const outPath = process.argv[3];
  if (!inPath || !outPath) {
    console.error('Usage: node ua-tour-analyze.js <input.json> <output.json>');
    process.exit(1);
  }
  const data = JSON.parse(fs.readFileSync(inPath, 'utf8'));
  const nodes = data.nodes || [];
  const edges = data.edges || [];
  const layers = data.layers || [];

  const nodeById = new Map();
  for (const n of nodes) nodeById.set(n.id, n);
  const nodeIdSet = new Set(nodeById.keys());

  // Only consider edges between actual graph nodes (ignore function:* targets)
  const nodeEdges = edges.filter(e => nodeIdSet.has(e.source) && nodeIdSet.has(e.target));

  // Fan-in / fan-out
  const fanIn = new Map();
  const fanOut = new Map();
  for (const id of nodeIdSet) { fanIn.set(id, 0); fanOut.set(id, 0); }
  for (const e of nodeEdges) {
    fanOut.set(e.source, fanOut.get(e.source) + 1);
    fanIn.set(e.target, fanIn.get(e.target) + 1);
  }

  const nm = id => (nodeById.get(id) || {}).name || id;

  const fanInRanking = [...fanIn.entries()]
    .map(([id, v]) => ({ id, fanIn: v, name: nm(id) }))
    .sort((a, b) => b.fanIn - a.fanIn).slice(0, 20);
  const fanOutRanking = [...fanOut.entries()]
    .map(([id, v]) => ({ id, fanOut: v, name: nm(id) }))
    .sort((a, b) => b.fanOut - a.fanOut).slice(0, 20);

  // Entry point candidates
  const codeEntryNames = new Set(['index.ts','index.js','main.ts','main.js','app.ts','app.js','server.ts','server.js','mod.rs','main.go','main.py','main.rs','manage.py','app.py','wsgi.py','asgi.py','run.py','__main__.py','Application.java','Main.java','Program.cs','config.ru','index.php','App.swift','Application.kt','main.cpp','main.c']);

  const fanOutVals = [...fanOut.values()].sort((a, b) => b - a);
  const top10idx = Math.max(0, Math.floor(fanOutVals.length * 0.10) - 1);
  const top10Threshold = fanOutVals.length ? fanOutVals[top10idx] : 0;
  const fanInVals = [...fanIn.values()].sort((a, b) => a - b);
  const bottom25idx = Math.max(0, Math.floor(fanInVals.length * 0.25) - 1);
  const bottom25Threshold = fanInVals.length ? fanInVals[bottom25idx] : 0;

  const entryScores = [];
  for (const n of nodes) {
    let score = 0;
    const fp = n.filePath || '';
    const depth = fp.split('/').length - 1;
    if (n.type === 'document') {
      if (n.name === 'README.md' && depth === 0) score += 5;
      else if (fp.endsWith('.md') && depth === 0) score += 2;
    } else if (n.type === 'file') {
      if (codeEntryNames.has(n.name)) score += 3;
      if (depth <= 1) score += 1;
      if (fanOut.get(n.id) >= top10Threshold && top10Threshold > 0) score += 1;
      if (fanIn.get(n.id) <= bottom25Threshold) score += 1;
    }
    if (score > 0) entryScores.push({ id: n.id, score, name: n.name, summary: n.summary || '' });
  }
  entryScores.sort((a, b) => b.score - a.score);
  const entryPointCandidates = entryScores.slice(0, 5);

  // BFS from top CODE entry point, following imports + calls forward
  // Build adjacency including function-hop resolution: a file "calls" a function
  // that is "contained" in another file -> treat as file->file edge.
  const funcToFile = new Map();
  for (const e of edges) {
    if (e.type === 'contains' && nodeIdSet.has(e.source) && !nodeIdSet.has(e.target)) {
      funcToFile.set(e.target, e.source);
    }
  }
  const adj = new Map();
  for (const id of nodeIdSet) adj.set(id, new Set());
  for (const e of edges) {
    if (e.type !== 'imports' && e.type !== 'calls' && e.type !== 'depends_on') continue;
    if (!nodeIdSet.has(e.source)) continue;
    let tgt = e.target;
    if (!nodeIdSet.has(tgt)) tgt = funcToFile.get(tgt);
    if (tgt && nodeIdSet.has(tgt) && tgt !== e.source) adj.get(e.source).add(tgt);
  }

  const codeEntry = entryScores.find(e => (nodeById.get(e.id) || {}).type === 'file');
  const startNode = codeEntry ? codeEntry.id : (nodes[0] && nodes[0].id);
  const order = [];
  const depthMap = {};
  if (startNode) {
    const q = [startNode];
    depthMap[startNode] = 0;
    while (q.length) {
      const cur = q.shift();
      order.push(cur);
      for (const nb of adj.get(cur) || []) {
        if (!(nb in depthMap)) { depthMap[nb] = depthMap[cur] + 1; q.push(nb); }
      }
    }
  }
  const byDepth = {};
  for (const [id, d] of Object.entries(depthMap)) {
    (byDepth[d] = byDepth[d] || []).push(id);
  }

  // Non-code inventory
  const nonCodeFiles = { documentation: [], infrastructure: [], data: [], config: [] };
  for (const n of nodes) {
    const rec = { id: n.id, name: n.name, type: n.type, summary: n.summary || '' };
    if (n.type === 'document') nonCodeFiles.documentation.push(rec);
    else if (['service', 'pipeline', 'resource'].includes(n.type)) nonCodeFiles.infrastructure.push(rec);
    else if (['table', 'schema', 'endpoint'].includes(n.type)) nonCodeFiles.data.push(rec);
    else if (n.type === 'config') nonCodeFiles.config.push(rec);
  }

  // Clusters: bidirectional pairs, expanded
  const adjSet = adj; // directed
  const pairKey = (a, b) => [a, b].sort().join('||');
  const clusterSeeds = new Set();
  const seedPairs = [];
  for (const a of nodeIdSet) {
    for (const b of adjSet.get(a)) {
      if (adjSet.get(b) && adjSet.get(b).has(a)) {
        const k = pairKey(a, b);
        if (!clusterSeeds.has(k)) { clusterSeeds.add(k); seedPairs.push([a, b]); }
      }
    }
  }
  // count undirected edges between any two nodes for expansion + edgeCount
  const undEdge = new Map();
  for (const a of nodeIdSet) for (const b of adjSet.get(a)) {
    const k = pairKey(a, b);
    undEdge.set(k, (undEdge.get(k) || 0) + 1);
  }
  const neighbors = new Map();
  for (const id of nodeIdSet) neighbors.set(id, new Set());
  for (const e of nodeEdges) {
    if (e.source === e.target) continue;
    neighbors.get(e.source).add(e.target);
    neighbors.get(e.target).add(e.source);
  }
  const clusters = [];
  for (const [a, b] of seedPairs) {
    const members = new Set([a, b]);
    // expand: add nodes connected to 2+ members
    for (const cand of nodeIdSet) {
      if (members.has(cand)) continue;
      let conn = 0;
      for (const m of members) if (neighbors.get(cand).has(m)) conn++;
      if (conn >= 2 && members.size < 5) members.add(cand);
    }
    const arr = [...members];
    let edgeCount = 0;
    for (const e of nodeEdges) {
      if (members.has(e.source) && members.has(e.target)) edgeCount++;
    }
    clusters.push({ nodes: arr, edgeCount });
  }
  // dedupe clusters by member set
  const seenC = new Set();
  const uniqClusters = [];
  for (const c of clusters.sort((x, y) => y.edgeCount - x.edgeCount)) {
    const k = [...c.nodes].sort().join('||');
    if (seenC.has(k)) continue;
    seenC.add(k);
    uniqClusters.push(c);
  }
  const topClusters = uniqClusters.slice(0, 10);

  // Node summary index
  const nodeSummaryIndex = {};
  for (const n of nodes) {
    nodeSummaryIndex[n.id] = { name: n.name, type: n.type, summary: n.summary || '' };
  }

  const result = {
    scriptCompleted: true,
    entryPointCandidates,
    fanInRanking,
    fanOutRanking,
    bfsTraversal: { startNode, order, depthMap, byDepth },
    nonCodeFiles,
    clusters: topClusters,
    layers: { count: layers.length, list: layers.map(l => ({ id: l.id, name: l.name, description: l.description })) },
    nodeSummaryIndex,
    totalNodes: nodes.length,
    totalEdges: edges.length
  };
  fs.writeFileSync(outPath, JSON.stringify(result, null, 2));
  console.log('analysis complete ->', outPath);
  process.exit(0);
}

try { main(); } catch (e) { console.error('FATAL:', e.stack || e.message); process.exit(1); }
