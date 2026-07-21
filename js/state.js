// Posledná hodnota nastavuje vzhľad objektu na ploche vrátane voliteľnej farby iconColor.
// shape: "sharp", "rounded", "circle", "chair" alebo "none".
// Ikona môže byť napr. "fa_house" alebo názov SVG súboru z assets, napr. "stol.svg".
export const TYPES=[
  ['round-table','Okrúhly stôl','fa_circle',140,140,8,{icon:false,text:true,shape:'circle'}],
  ['rect-table','Obdĺžnikový stôl','fa_table_cells_large',180,100,8,{icon:false,text:true,shape:'rounded'}],
  ['head-table','Hlavný svadobný stôl','fa_rings_wedding',260,90,8,{icon:true,text:false,shape:'rounded'}],
  ['chair','Stolička','fa_seat',55,55,0,{icon:true,text:true,shape:'chair'}],
  ['dj','DJ pult','fa_turntable',150,80,0,{icon:true,text:true,shape:'rounded'}],
  ['speaker','Reproduktor','fa_speaker',55,55,0,{icon:true,text:false,shape:'rounded'}],
  ['bar','Bar','fa_martini_glass_citrus',170,70,0,{icon:true,text:true,shape:'rounded'}],
  ['dance-floor','Tanečný parket','fa_shoe_prints',250,200,0,{icon:true,text:true,shape:'sharp'}],
  ['decoration','Dekorácia','fa_balloon',70,70,0,{icon:true,text:true,shape:'none',iconColor:'var(--decoration-color)'}],
  ['plant','Rastlina','fa_flower_tulip',70,70,0,{icon:true,text:true,shape:'none',iconColor:'var(--plant-color)'}],
  ['stage','Pódium','fa_masks_theater',220,110,0,{icon:true,text:true,shape:'rounded'}],
  ['photo','Fotokútik','fa_camera_retro',180,120,0,{icon:true,text:true,shape:'rounded'}]
];
export const PERSON_ICONS={adult:'fa_user',child:'fa_child_dress'};
export const ALLERGIES=['Lepok','Laktóza','Arašidy','Orechy','Vajcia','Ryby','Sója','Iné'];
const uid=()=>globalThis.crypto&&typeof globalThis.crypto.randomUUID==='function'
  ?globalThis.crypto.randomUUID()
  :'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g,c=>{const r=Math.random()*16|0,v=c==='x'?r:(r&3|8);return v.toString(16)});
export function initialState(){const t1=uid(),t2=uid(),t3=uid(),g1=uid(),g2=uid(),g3=uid(),g4=uid();return{schemaVersion:1,projectId:uid(),wedding:{date:'2026-09-12',contactName:'Jana',phone:'',email:'',note:''},settings:{zoom:0.72,panX:60,panY:35},items:[
  {id:uid(),type:'head-table',name:'Hlavný stôl',x:500,y:90,width:520,height:100,rotation:0,number:0,seats:10,note:'',locked:false},
  {id:t1,type:'round-table',name:'Stôl 1',x:380,y:350,width:180,height:180,rotation:0,number:1,seats:8,note:'Rodina nevesty',locked:false},
  {id:t2,type:'round-table',name:'Stôl 2',x:800,y:320,width:180,height:180,rotation:0,number:2,seats:8,note:'',locked:false},
  {id:t3,type:'round-table',name:'Stôl 3',x:610,y:670,width:180,height:180,rotation:0,number:3,seats:8,note:'',locked:false},
  {id:uid(),type:'dj',name:'DJ',x:90,y:450,width:150,height:85,rotation:0,number:1,seats:0,note:'',locked:false},{id:uid(),type:'bar',name:'Bar',x:1450,y:880,width:190,height:75,rotation:0,number:1,seats:0,note:'',locked:false},{id:uid(),type:'dance-floor',name:'Tanečný parket',x:1100,y:350,width:360,height:300,rotation:0,number:1,seats:0,note:'',locked:false}],guests:[
  {id:g1,firstName:'Zuzana',lastName:'Nováková',personType:'adult',tableId:t2,seatNumber:1,menu:'Klasické',allergies:['Arašidy'],note:'',companion:'Peter Novák',group:'Novákovci',rsvp:'yes',color:'#e99499'},
  {id:g2,firstName:'Peter',lastName:'Novák',personType:'adult',tableId:t2,seatNumber:2,menu:'Klasické',allergies:[],note:'',companion:'Zuzana Nováková',group:'Novákovci',rsvp:'yes',color:'#91addc'},
  {id:g3,firstName:'Anna',lastName:'Kováčová',personType:'adult',tableId:t2,seatNumber:3,menu:'Bezlepkové',allergies:['Lepok'],note:'',companion:'',group:'Kováčovci',rsvp:'yes',color:'#d9b776'},
  {id:g4,firstName:'Nina',lastName:'Kováčová',personType:'child',tableId:t2,seatNumber:4,menu:'Detské',allergies:[],note:'',companion:'Anna Kováčová',group:'Kováčovci',rsvp:'yes',color:'#b09ad8'},
  {id:uid(),firstName:'Tomáš',lastName:'Horváth',personType:'adult',tableId:'',seatNumber:null,menu:'Vegetariánske',allergies:[],note:'',companion:'',group:'Priatelia',rsvp:'pending',color:'#85ad9a'}],meta:{createdAt:new Date().toISOString(),updatedAt:new Date().toISOString(),submittedAt:null}}}
export function load(){try{const raw=localStorage.getItem('wedding-planner-v1');if(!raw)return initialState();const s=JSON.parse(raw);if(!s.items||!s.guests||!s.wedding)throw Error();delete s.wedding.name;delete s.wedding.venue;s.guests.forEach(g=>delete g.childAge);return s}catch{return initialState()}}
export function validProject(s){return !!(s&&s.schemaVersion===1&&Array.isArray(s.items)&&Array.isArray(s.guests)&&s.wedding&&s.settings)}
export function download(data,name,type='application/json'){const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([data],{type}));a.download=name;a.click();setTimeout(()=>URL.revokeObjectURL(a.href),1000)}
