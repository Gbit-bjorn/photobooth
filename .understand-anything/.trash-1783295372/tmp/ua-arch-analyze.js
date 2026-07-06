#!/usr/bin/env node
'use strict';

function main() {
  const inPath = process.argv[2];
  const outPath = process.argv[3];
  if (!inPath || !outPath) {
    console.error('Usage: node ua-arch-analyze.js <input.json> <output.json>');
    process.exit(1);
  }
  const fs = require('fs');
  const data = JSON.parse(fs.readFileSync(inPath, 'utf8'));
  const fileNodes = data.fileNodes || [];
  const importEdges = data.importEdges || [];
  const allEdges = data.allEdges || [];

  const nodeById = {};
  fileNodes.forEach(n => { nodeById[n.id] = n; });
  const isFileNode = id => Object.prototype.hasOwnProperty.call(nodeById, id);

  // ---- common prefix of directories ----
  const paths = fileNodes.map(n => n.filePath);
  function dirSegments(p) {
    const parts = p.split('/');
    parts.pop(); // remove filename
    return parts;
  }
  // common prefix across directory segments
  let commonPrefix = null;
  fileNodes.forEach(n => {
    const segs = dirSegments(n.filePath);
    if (commonPrefix === null) { commonPrefix = segs.slice(); }
    else {
      let i = 0;
      while (i < commonPrefix.length && i < segs.length && commonPrefix[i] === segs[i]) i++;
      commonPrefix = commonPrefix.slice(0, i);
    }
  });
  if (commonPrefix === null) commonPrefix = [];
  const prefixLen = commonPrefix.length;

  function groupOf(n) {
    const segs = dirSegments(n.filePath);
    const rel = segs.slice(prefixLen);
    if (rel.length === 0) return 'root';
    return rel[0];
  }

  // ---- A. Directory grouping ----
  const directoryGroups = {};
  fileNodes.forEach(n => {
    const g = groupOf(n);
    (directoryGroups[g] = directoryGroups[g] || []).push(n.id);
  });
  const groupByNode = {};
  Object.keys(directoryGroups).forEach(g => directoryGroups[g].forEach(id => { groupByNode[id] = g; }));

  // ---- B. Node type grouping ----
  const nodeTypeGroups = {};
  fileNodes.forEach(n => {
    (nodeTypeGroups[n.type] = nodeTypeGroups[n.type] || []).push(n.id);
  });

  // ---- C. Import adjacency: fan-in / fan-out ----
  const fanOut = {}, fanIn = {};
  fileNodes.forEach(n => { fanOut[n.id] = 0; fanIn[n.id] = 0; });
  importEdges.forEach(e => {
    if (isFileNode(e.source) && isFileNode(e.target)) {
      fanOut[e.source] = (fanOut[e.source] || 0) + 1;
      fanIn[e.target] = (fanIn[e.target] || 0) + 1;
    }
  });

  // ---- D. Cross-category dependency (allEdges between differing node types) ----
  const ccMap = {};
  allEdges.forEach(e => {
    if (!isFileNode(e.source) || !isFileNode(e.target)) return; // only file-level nodes
    const st = nodeById[e.source].type;
    const tt = nodeById[e.target].type;
    if (st === tt) return; // cross-category only
    const key = st + '|' + tt + '|' + e.type;
    ccMap[key] = (ccMap[key] || 0) + 1;
  });
  const crossCategoryEdges = Object.keys(ccMap).map(k => {
    const [fromType, toType, edgeType] = k.split('|');
    return { fromType, toType, edgeType, count: ccMap[k] };
  }).sort((a, b) => b.count - a.count);

  // ---- E. Inter-group import frequency (use import + depends_on among file nodes) ----
  const depEdgeTypes = new Set(['imports', 'depends_on']);
  const interMap = {};
  allEdges.forEach(e => {
    if (!depEdgeTypes.has(e.type)) return;
    if (!isFileNode(e.source) || !isFileNode(e.target)) return;
    const gs = groupByNode[e.source], gt = groupByNode[e.target];
    if (gs === undefined || gt === undefined) return;
    if (gs === gt) return;
    const key = gs + '|' + gt;
    interMap[key] = (interMap[key] || 0) + 1;
  });
  const interGroupImports = Object.keys(interMap).map(k => {
    const [from, to] = k.split('|');
    return { from, to, count: interMap[k] };
  }).sort((a, b) => b.count - a.count);

  // ---- F. Intra-group density ----
  const intraGroupDensity = {};
  const groupTotalEdges = {}, groupInternalEdges = {};
  Object.keys(directoryGroups).forEach(g => { groupTotalEdges[g] = 0; groupInternalEdges[g] = 0; });
  allEdges.forEach(e => {
    if (!depEdgeTypes.has(e.type)) return;
    if (!isFileNode(e.source) || !isFileNode(e.target)) return;
    const gs = groupByNode[e.source], gt = groupByNode[e.target];
    if (gs !== undefined) groupTotalEdges[gs]++;
    if (gt !== undefined && gt !== gs) groupTotalEdges[gt]++;
    if (gs !== undefined && gs === gt) { groupInternalEdges[gs]++; groupTotalEdges[gs]--; /* counted once */ }
  });
  Object.keys(directoryGroups).forEach(g => {
    const total = groupTotalEdges[g] + groupInternalEdges[g];
    intraGroupDensity[g] = {
      internalEdges: groupInternalEdges[g],
      totalEdges: total,
      density: total > 0 ? +(groupInternalEdges[g] / total).toFixed(3) : 0
    };
  });

  // ---- G. Directory pattern matching ----
  const dirPatterns = [
    [['routes','api','controllers','endpoints','handlers','routers','controller','serializers','blueprints'], 'api'],
    [['services','core','lib','domain','logic','signals','composables','mailers','jobs','channels','internal'], 'service'],
    [['models','db','data','persistence','repository','entities','migrations','entity','sql','database'], 'data'],
    [['components','views','pages','ui','layouts','screens'], 'ui'],
    [['middleware','plugins','interceptors','guards'], 'middleware'],
    [['utils','helpers','common','shared','tools','pkg','templatetags'], 'utility'],
    [['config','constants','env','settings','management','commands'], 'config'],
    [['__tests__','test','tests','spec','specs'], 'test'],
    [['types','interfaces','schemas','contracts','dtos','dto','request','response'], 'types'],
    [['hooks'], 'hooks'],
    [['store','state','reducers','actions','slices'], 'state'],
    [['assets','static','public'], 'assets'],
    [['cmd','bin'], 'entry'],
    [['docs','documentation','wiki'], 'documentation'],
    [['deploy','deployment','infra','infrastructure','docker','k8s','kubernetes','helm','charts','terraform','tf'], 'infrastructure'],
    [['.github','.gitlab','.circleci'], 'ci-cd']
  ];
  function matchDir(name) {
    for (const [names, label] of dirPatterns) {
      if (names.indexOf(name) !== -1) return label;
    }
    return null;
  }
  const patternMatches = {};
  Object.keys(directoryGroups).forEach(g => {
    const m = matchDir(g);
    if (m) patternMatches[g] = m;
    else patternMatches[g] = null;
  });

  // ---- File-level pattern helpers ----
  function fileLevelPattern(fp) {
    const base = fp.split('/').pop();
    if (/(\.test\.|\.spec\.)/.test(base) || /^test_.*\.py$/.test(base) || /_test\.go$/.test(base) || /Test\.java$/.test(base) || /_spec\.rb$/.test(base) || /Test\.php$/.test(base) || /Tests\.cs$/.test(base)) return 'test';
    if (/\.d\.ts$/.test(base)) return 'types';
    if (/\.(md|rst)$/.test(base)) return 'documentation';
    if (/\.sql$/.test(base)) return 'data';
    if (/\.(graphql|gql|proto)$/.test(base)) return 'types';
    if (base === 'Dockerfile' || /^docker-compose\./.test(base)) return 'infrastructure';
    if (/\.(tf|tfvars)$/.test(base)) return 'infrastructure';
    if (base === 'Makefile') return 'infrastructure';
    return null;
  }

  // ---- H. Deployment topology ----
  const infraFiles = [];
  let hasDockerfile = false, hasCompose = false, hasK8s = false, hasTerraform = false, hasCI = false;
  fileNodes.forEach(n => {
    const fp = n.filePath, base = fp.split('/').pop();
    if (base === 'Dockerfile' || /^Dockerfile\./.test(base)) { hasDockerfile = true; infraFiles.push(fp); }
    if (/^docker-compose/.test(base)) { hasCompose = true; infraFiles.push(fp); }
    if (/\.(ya?ml)$/.test(base) && /(k8s|kubernetes|manifest)/i.test(fp)) { hasK8s = true; infraFiles.push(fp); }
    if (/\.(tf|tfvars)$/.test(base)) { hasTerraform = true; infraFiles.push(fp); }
    if (/\.github\/workflows/.test(fp) || base === '.gitlab-ci.yml' || base === 'Jenkinsfile') { hasCI = true; infraFiles.push(fp); }
  });
  const deploymentTopology = { hasDockerfile, hasCompose, hasK8s, hasTerraform, hasCI, infraFiles };

  // ---- I. Data pipeline ----
  const schemaFiles = [], migrationFiles = [], dataModelFiles = [], apiHandlerFiles = [];
  fileNodes.forEach(n => {
    const fp = n.filePath, base = fp.split('/').pop();
    const tags = n.tags || [];
    if (/\.(sql|graphql|gql|proto|prisma)$/.test(base)) schemaFiles.push(fp);
    if (/migrations?\//.test(fp)) migrationFiles.push(fp);
    if (tags.indexOf('data-model') !== -1) dataModelFiles.push(fp);
    if (tags.indexOf('api-handler') !== -1) apiHandlerFiles.push(fp);
  });
  const dataPipeline = { schemaFiles, migrationFiles, dataModelFiles, apiHandlerFiles };

  // ---- J. Documentation coverage ----
  const docNodes = fileNodes.filter(n => n.type === 'document' || fileLevelPattern(n.filePath) === 'documentation');
  const groupsWithDocsSet = new Set();
  docNodes.forEach(n => { groupsWithDocsSet.add(groupByNode[n.id]); });
  const totalGroups = Object.keys(directoryGroups).length;
  const undocumentedGroups = Object.keys(directoryGroups).filter(g => !groupsWithDocsSet.has(g));
  const docCoverage = {
    groupsWithDocs: groupsWithDocsSet.size,
    totalGroups,
    coverageRatio: totalGroups > 0 ? +(groupsWithDocsSet.size / totalGroups).toFixed(2) : 0,
    undocumentedGroups
  };

  // ---- K. Dependency direction ----
  const pairNet = {};
  interGroupImports.forEach(({ from, to, count }) => {
    const key = [from, to].sort().join('|');
    pairNet[key] = pairNet[key] || {};
    pairNet[key][from + '>' + to] = count;
  });
  const dependencyDirection = [];
  Object.keys(pairNet).forEach(key => {
    const [a, b] = key.split('|');
    const ab = pairNet[key][a + '>' + b] || 0;
    const ba = pairNet[key][b + '>' + a] || 0;
    if (ab > ba) dependencyDirection.push({ dependent: a, dependsOn: b });
    else if (ba > ab) dependencyDirection.push({ dependent: b, dependsOn: a });
  });

  // ---- fileStats ----
  const filesPerGroup = {};
  Object.keys(directoryGroups).forEach(g => { filesPerGroup[g] = directoryGroups[g].length; });
  const nodeTypeCounts = {};
  Object.keys(nodeTypeGroups).forEach(t => { nodeTypeCounts[t] = nodeTypeGroups[t].length; });

  const out = {
    scriptCompleted: true,
    commonPrefix: commonPrefix.join('/'),
    directoryGroups,
    nodeTypeGroups,
    crossCategoryEdges,
    interGroupImports,
    intraGroupDensity,
    patternMatches,
    deploymentTopology,
    dataPipeline,
    docCoverage,
    dependencyDirection,
    fileStats: {
      totalFileNodes: fileNodes.length,
      filesPerGroup,
      nodeTypeCounts
    },
    fileFanIn: fanIn,
    fileFanOut: fanOut
  };
  fs.writeFileSync(outPath, JSON.stringify(out, null, 2));
  process.exit(0);
}

try { main(); } catch (e) { console.error(e && e.stack ? e.stack : String(e)); process.exit(1); }
