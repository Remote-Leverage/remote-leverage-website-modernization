import { readFileSync, writeFileSync } from 'node:fs';
import zlib from 'node:zlib';

export function decode(file) {
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
  if (depth !== 8) throw new Error('bit depth ' + depth);
  const ch = { 0:1, 2:3, 4:2, 6:4 }[ctype];
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
      if (ft===1) v+=a; else if (ft===2) v+=bb; else if (ft===3) v+=(a+bb)>>1;
      else if (ft===4) { const p=a+bb-c,pa=Math.abs(p-a),pb=Math.abs(p-bb),pc=Math.abs(p-c); v += (pa<=pb&&pa<=pc)?a:(pb<=pc?bb:c); }
      cur[x] = v & 0xff;
    }
  }
  return { w, h, ch, data: out };
}

function crc32(buf) {
  let c, table = crc32.t;
  if (!table) { table = crc32.t = []; for (let n=0;n<256;n++){ c=n; for(let k=0;k<8;k++) c = c&1 ? 0xEDB88320 ^ (c>>>1) : c>>>1; table[n]=c>>>0; } }
  let crc = 0xFFFFFFFF;
  for (let i=0;i<buf.length;i++) crc = table[(crc ^ buf[i]) & 0xFF] ^ (crc >>> 8);
  return (crc ^ 0xFFFFFFFF) >>> 0;
}
function chunk(type, data) {
  const len = Buffer.alloc(4); len.writeUInt32BE(data.length);
  const td = Buffer.concat([Buffer.from(type,'ascii'), data]);
  const crc = Buffer.alloc(4); crc.writeUInt32BE(crc32(td));
  return Buffer.concat([len, td, crc]);
}
/** Write an RGB(A) buffer out as a PNG. */
export function encode(file, w, h, ch, data) {
  const stride = w*ch;
  const raw = Buffer.alloc(h*(stride+1));
  for (let y=0;y<h;y++){ raw[y*(stride+1)] = 0; data.copy(raw, y*(stride+1)+1, y*stride, (y+1)*stride); }
  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(w,0); ihdr.writeUInt32BE(h,4); ihdr[8]=8; ihdr[9]= ch===4?6:(ch===3?2:0); ihdr[10]=0; ihdr[11]=0; ihdr[12]=0;
  writeFileSync(file, Buffer.concat([
    Buffer.from([0x89,0x50,0x4E,0x47,0x0D,0x0A,0x1A,0x0A]),
    chunk('IHDR', ihdr),
    chunk('IDAT', zlib.deflateSync(raw, {level:6})),
    chunk('IEND', Buffer.alloc(0)),
  ]));
}
/** Extract rows [y0,y1) at full width, optionally scaled down by integer factor. */
export function crop(img, y0, y1, scale = 1) {
  y0 = Math.max(0, y0); y1 = Math.min(img.h, y1);
  const sh = Math.floor((y1-y0)/scale), sw = Math.floor(img.w/scale);
  const out = Buffer.alloc(sw*sh*img.ch);
  for (let y=0;y<sh;y++) for (let x=0;x<sw;x++) {
    let r=0,g=0,b=0,a=0,n=0;
    for (let dy=0;dy<scale;dy++) for (let dx=0;dx<scale;dx++){
      const i=((y0+y*scale+dy)*img.w + (x*scale+dx))*img.ch;
      r+=img.data[i]; g+=img.data[i+1]; b+=img.data[i+2]; a+= img.ch===4?img.data[i+3]:255; n++;
    }
    const o=(y*sw+x)*img.ch;
    out[o]=r/n; out[o+1]=g/n; out[o+2]=b/n; if(img.ch===4) out[o+3]=a/n;
  }
  return { w: sw, h: sh, ch: img.ch, data: out };
}
