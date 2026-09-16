const sharp=require('sharp');
(async()=>{
  const [,,src,L,T,W,H,thr]=process.argv; const t=thr?+thr:200;
  const {data,info}=await sharp(src).extract({left:+L,top:+T,width:+W,height:+H}).greyscale().raw().toBuffer({resolveWithObject:true});
  let x0=1e9,y0=1e9,x1=-1,y1=-1;
  for(let y=0;y<info.height;y++)for(let x=0;x<info.width;x++){
    if(data[y*info.width+x]>t){ if(x<x0)x0=x; if(x>x1)x1=x; if(y<y0)y0=y; if(y>y1)y1=y; }
  }
  console.log(`light bbox abs: x ${x0+ +L}..${x1+ +L} (w ${x1-x0+1}), y ${y0+ +T}..${y1+ +T} (h ${y1-y0+1})`);
})();
