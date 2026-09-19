import { decode } from './pnglib.mjs';
const img = decode(process.argv[2]);
const px=(x,y)=>{const i=(y*img.w+x)*img.ch;return [img.data[i],img.data[i+1],img.data[i+2]];};
// The CTA pill is --color-brand-magenta #F90066 — unmistakable, and a reliable anchor.
const isMag=p=>p[0]>215&&p[0]<255&&p[1]<60&&p[2]>70&&p[2]<130;
let runs=[],cur=null;
for(let y=0;y<img.h;y++){
  let n=0,minx=1e9,maxx=-1;
  for(let x=0;x<img.w;x++){ if(isMag(px(x,y))){n++; if(x<minx)minx=x; if(x>maxx)maxx=x;} }
  if(n>40){ if(!cur)cur={y0:y,y1:y,minx,maxx}; cur.y1=y; cur.minx=Math.min(cur.minx,minx); cur.maxx=Math.max(cur.maxx,maxx);}
  else { if(cur){runs.push(cur);cur=null;} }
}
if(cur)runs.push(cur);
console.log(`${process.argv[2].split('/').pop()}  (h=${img.h})`);
for(const r of runs) console.log(`  pill y ${r.y0}..${r.y1} (h=${r.y1-r.y0+1})  x ${r.minx}..${r.maxx} (w=${r.maxx-r.minx+1})`);
