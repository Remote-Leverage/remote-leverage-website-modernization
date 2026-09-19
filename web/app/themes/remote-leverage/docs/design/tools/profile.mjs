import { decode } from './pnglib.mjs';
const img = decode(process.argv[2]);
const [y0,y1,x0,x1] = process.argv.slice(3,7).map(Number);
const px=(x,y)=>{const i=(y*img.w+x)*img.ch;return [img.data[i],img.data[i+1],img.data[i+2]];};
const hist=new Map();
for(let y=y0;y<y1;y++) for(let x=x0;x<x1;x++){const k=px(x,y).join(',');hist.set(k,(hist.get(k)||0)+1);}
const bg=[...hist.entries()].sort((a,b)=>b[1]-a[1])[0][0].split(',').map(Number);
let runs=[], cur=null;
for(let y=y0;y<y1;y++){
  let n=0,minx=1e9,maxx=-1;
  for(let x=x0;x<x1;x++){const p=px(x,y);
    if(Math.abs(p[0]-bg[0])+Math.abs(p[1]-bg[1])+Math.abs(p[2]-bg[2])>90){n++;if(x<minx)minx=x;if(x>maxx)maxx=x;}}
  if(n>0){ if(!cur) cur={y0:y,y1:y,minx,maxx,px:0}; cur.y1=y; cur.minx=Math.min(cur.minx,minx); cur.maxx=Math.max(cur.maxx,maxx); cur.px+=n; }
  else { if(cur){runs.push(cur);cur=null;} }
}
if(cur) runs.push(cur);
console.log(`bg=rgb(${bg})  ${runs.length} ink runs in y ${y0}..${y1}`);
for(const r of runs) console.log(`  y ${String(r.y0).padStart(5)}..${String(r.y1).padStart(5)} (h=${String(r.y1-r.y0+1).padStart(3)})  x ${String(r.minx).padStart(3)}..${String(r.maxx).padStart(3)} (w=${String(r.maxx-r.minx+1).padStart(3)})  px=${r.px}`);
