const http = require('http');
const fs = require('fs');
const path = require('path');
const mysql = require('mysql2/promise');

const PORT = 8080;
const ROOT = __dirname;

const DB = {
  host: 'webdev.iyaserver.com',
  port: 3306,
  user: 'wthoman_data_guest',
  password: 'dataproject',
  database: 'wthoman_ai_data_center_data',
  connectTimeout: 10000,
};

const MIME = {
  '.html': 'text/html',
  '.css':  'text/css',
  '.js':   'application/javascript',
  '.json': 'application/json',
  '.png':  'image/png',
  '.svg':  'image/svg+xml',
  '.ico':  'image/x-icon',
  '.otf':  'font/otf',
  '.ttf':  'font/ttf',
  '.woff': 'font/woff',
  '.woff2':'font/woff2',
};

const ALLOWED_TABLES = ['gpu_specs', 'datacenter_components', 'gpu_clusters', 'regional_data_annex', 'world_data_annex'];

const QUERIES = {
  gpu_specs: `
    SELECT manufacturer, productName, releaseYear, memSize, memBusWidth,
           gpuClock, memClock, unifiedShader, tmu, rop, pixelShader,
           vertexShader, igp, bus, memType, gpuChip
    FROM gpu_specs
    WHERE memSize IS NOT NULL
      AND unifiedShader IS NOT NULL
      AND gpuClock IS NOT NULL
      AND memClock IS NOT NULL`,

  datacenter_components: `
    SELECT year, component, consumption_twh
    FROM datacenter_components
    ORDER BY year ASC, component ASC`,

  regional_data_annex: `
    SELECT metric, unit, region, year, scenario, value
    FROM regional_data_annex`,

  world_data_annex: `
    SELECT metric, segment, base_2020, base_2023, base_2024,
           liftoff_2030, liftoff_2035, high_efficiency_2030,
           high_efficiency_2035, headwinds_2030, headwinds_2035
    FROM world_data_annex`,
};

const GPU_CLUSTERS_COLS = [
  'name','status','certainty','single_cluster','h100_equivalents',
  'chip_type_primary','chip_quantity_primary','country','owner',
  'first_operational_date','sector','power_capacity_mw','location',
  'builds_upon','superseded_by','possible_duplicate','possible_duplicate_of',
  'chip_type_secondary','chip_quantity_secondary','total_number_of_ai_chips',
  'gpu_supplier_primary','gpu_supplier_secondary','include_in_standard_analysis',
  'exclude','rank_when_first_operational','energy_efficiency','decommissioned_date',
  'largest_existing_cluster_when_first_operational',
  'pct_of_largest_cluster_when_first_operational'
];

async function handleDatabase(req, res) {
  const url = new URL(req.url, `http://localhost:${PORT}`);
  const only = url.searchParams.get('table');

  if (only !== null && !ALLOWED_TABLES.includes(only)) {
    res.writeHead(400, {'Content-Type': 'application/json'});
    res.end(JSON.stringify({error: 'Unknown table', allowed: ALLOWED_TABLES}));
    return;
  }

  let conn;
  try {
    conn = await mysql.createConnection(DB);
  } catch (e) {
    console.error('DB connect failed:', e.message);
    res.writeHead(500, {'Content-Type': 'application/json'});
    res.end(JSON.stringify({error: 'Database connection failed'}));
    return;
  }

  try {
    const response = {};
    const tables = only ? [only] : ALLOWED_TABLES;

    for (const table of tables) {
      if (table === 'gpu_clusters') {
        const [colRows] = await conn.execute(`SHOW COLUMNS FROM \`gpu_clusters\``);
        const existing = new Set(colRows.map(r => r.Field));
        const cols = GPU_CLUSTERS_COLS.filter(c => existing.has(c));
        const selectSql = cols.length
          ? cols.map(c => `\`${c}\``).join(', ')
          : '*';
        const [rows] = await conn.execute(`SELECT ${selectSql} FROM \`gpu_clusters\``);
        response.gpu_clusters = rows;
      } else {
        const [rows] = await conn.execute(QUERIES[table]);
        response[table] = rows;
      }
    }

    res.writeHead(200, {
      'Content-Type': 'application/json; charset=utf-8',
      'Access-Control-Allow-Origin': '*',
      'Cache-Control': 'public, max-age=300',
    });
    res.end(JSON.stringify(response));
  } catch (e) {
    console.error('Query failed:', e.message);
    res.writeHead(500, {'Content-Type': 'application/json'});
    res.end(JSON.stringify({error: 'Query failed', detail: e.message}));
  } finally {
    await conn.end();
  }
}

function serveStatic(req, res) {
  let filePath = path.join(ROOT, req.url === '/' ? '/index.html' : req.url);
  // strip query strings
  filePath = filePath.split('?')[0];
  const ext = path.extname(filePath);
  const mime = MIME[ext] || 'application/octet-stream';

  fs.readFile(filePath, (err, data) => {
    if (err) {
      res.writeHead(404);
      res.end('Not found');
      return;
    }
    res.writeHead(200, {'Content-Type': mime});
    res.end(data);
  });
}

const server = http.createServer(async (req, res) => {
  const url = req.url.split('?')[0];

  if (req.method === 'OPTIONS') {
    res.writeHead(204, {'Access-Control-Allow-Origin': '*'});
    res.end();
    return;
  }

  if (url === '/database.php') {
    await handleDatabase(req, res);
  } else {
    serveStatic(req, res);
  }
});

server.listen(PORT, () => {
  console.log(`Server running at http://localhost:${PORT}`);
  console.log(`database.php endpoint: http://localhost:${PORT}/database.php`);
});
