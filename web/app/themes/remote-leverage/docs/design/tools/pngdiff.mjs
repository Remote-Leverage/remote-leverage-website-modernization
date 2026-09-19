import { readFileSync } from 'node:fs';
import zlib from 'node:zlib';

function decode(file) {
  const b = readFileSync(file);
  let i = 8, w=0,h=0,depth=0,ctype=0;
  const idat = [];
  while (i < b.length) {
    const len = b.readUInt32BE(i);
    const type = b.toString('ascii', i+4, i+8);
    const data = b.subarray(i+8, i+8+len);
    if (type === 'IHDR') { w=data.readUInt32BE(0); h=data.readUInt32BE(4); depth=data[8]; ctype=data[9]; }
    else if (type === 'IDAT') idat.push(data);
    else if (type === 'IEND') break;
    i += 12 + len;
  }
  if (depth !== 8) throw new Error('unsupported bit depth ' + depth);
  const ch = { 0:1, 2:3, 4:2, 6:4 }[ctype];
  if (!ch) throw new Error('unsupported colour type ' + ctype);
  const raw = zlib.inflateSync(Buffer.concat(idat));
  const stride = w * ch;
  const out = Buffer.alloc(h * stride);
  let pos = 0;
  for (let y = 0; y < h; y++) {
    const ft = raw[pos++];
    const line = raw.subarray(pos, pos + stride); pos += stride;
    const cur = out.subarray(y*stride, (y+1)*stride);
    const prev = y ? out.subarray((y-1)*stride, y*stride) : null;
    for (let x = 0; x < stride; x++) {
      const a = x >= ch ? cur[x-ch] : 0;
      const bb = prev ? prev[x] : 0;
      const c = (prev && x >= ch) ? prev[x-ch] : 0;
      let v = line[x];
      switch (ft) {
        case 0: break;
        case 1: v += a; break;
        case 2: v += bb; break;
        case 3: v += (a + bb) >> 1; break;
        case 4: {
          const p = a + bb - c, pa = Math.abs(p-a), pb = Math.abs(p-bb), pc = Math.abs(p-c);
          v += (pa <= pb && pa <= pc) ? a : (pb <= pc ? bb : c);
          break;
        }
        default: throw new Error('bad filter ' + ft);
      }
      cur[x] = v & 0xff;
    }
  }
  return { w, h, ch, data: out };
}

const [fa, fb] = process.argv.slice(2);
const A = decode(fa), B = decode(fb);
if (A.w !== B.w || A.h !== B.h) { console.log(`SIZE MISMATCH ${A.w}x${A.h} vs ${B.w}x${B.h}`); process.exit(1); }

const THRESH = Number(process.env.THRESH ?? 12);
let diff = 0;
const rows = new Map();
for (let y = 0; y < A.h; y++) {
  let rowDiff = 0;
  for (let x = 0; x < A.w; x++) {
    const ia = (y*A.w + x) * A.ch, ib = (y*B.w + x) * B.ch;
    const d = Math.abs(A.data[ia]-B.data[ib]) + Math.abs(A.data[ia+1]-B.data[ib+1]) + Math.abs(A.data[ia+2]-B.data[ib+2]);
    if (d > THRESH) { rowDiff++; diff++; }
  }
  if (rowDiff > 0) rows.set(y, rowDiff);
}
const total = A.w * A.h;
console.log(`size ${A.w}x${A.h}  differing px ${diff} / ${total}  = ${(diff/total*100).toFixed(3)}%  (per-channel threshold ${THRESH})`);

// contiguous bands of differing rows
const ys = [...rows.keys()].sort((a,b)=>a-b);
const bands = [];
for (const y of ys) {
  const last = bands[bands.length-1];
  if (last && y - last.end <= 8) { last.end = y; last.px += rows.get(y); }
  else bands.push({ start: y, end: y, px: rows.get(y) });
}
bands.sort((a,b)=>b.px-a.px);
console.log(`\ntop differing bands (y-range, differing px, peak px/row):`);
for (const band of bands.slice(0, 25)) {
  let peak = 0;
  for (let y = band.start; y <= band.end; y++) peak = Math.max(peak, rows.get(y) ?? 0);
  console.log(`  y ${String(band.start).padStart(6)}–${String(band.end).padStart(6)}  px ${String(band.px).padStart(8)}  peak ${peak}/${A.w}`);
}
console.log(`\n${bands.length} band(s) total`);
