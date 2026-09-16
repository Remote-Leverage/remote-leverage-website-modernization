const sharp=require('sharp');
(async()=>{
  const [,,src,L,T,W,H,thr]=process.argv;
  const t=thr?+thr:128;
  const {data,info}=await sharp(src).extract({left:+L,top:+T,width:+W,height:+H}).greyscale().raw().toBuffer({resolveWithObject:true});
  let x0=1e9,y0=1e9,x1=-1,y1=-1;
  const rows=[];
  for(let y=0;y<info.height;y++){let any=false;
    for(let x=0;x<info.width;x++){ if(data[y*info.width+x]<t){any=true; if(x<x0)x0=x; if(x>x1)x1=x; if(y<y0)y0=y; if(y>y1)y1=y;} }
    rows.push(any);
  }
  // find dark row bands
  const bands=[]; let s=null;
  rows.forEach((v,i)=>{ if(v&&s===null)s=i; if(!v&&s!==null){bands.push([s+ +T,i-1+ +T,i-s]); s=null;} });
  if(s!==null)bands.push([s+ +T,rows.length-1+ +T,rows.length-s]);
  console.log(`bbox abs: x ${x0+ +L}..${x1+ +L} (w ${x1-x0+1}), y ${y0+ +T}..${y1+ +T} (h ${y1-y0+1})`);
  console.log('dark row bands [absTop,absBot,height]:', JSON.stringify(bands.slice(0,14)));
})();
