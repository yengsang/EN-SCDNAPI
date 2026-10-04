import definition from '../data/navigation.json';
import type {Endpoint} from './catalog';

type Definition = {id:string; title:string; modules?:Definition[]; groups?:Definition[]; operations?:string[]};
export type ApiNavigation = {id:string; title:string; children:ApiNavigation[]; endpoints:Endpoint[]};
export type EndpointGroup = {id:string; label:string; endpoints:Endpoint[]};

export function buildNavigation(catalog:Endpoint[]):ApiNavigation[] {
  const lookup=new Map(catalog.map(e=>[e.method+' '+e.path,e]));
  const used=new Set<string>();
  function convert(item:Definition,parent=''):ApiNavigation {
    const id=parent+'/'+item.id;
    const endpoints=(item.operations||[]).map(key=>lookup.get(key)).filter((e):e is Endpoint=>!!e);
    endpoints.forEach(e=>used.add(e.id));
    let children=[...(item.modules||[]),...(item.groups||[])].map(n=>convert(n,id)).filter(n=>n.children.length||n.endpoints.length);
    if(children.length===1&&children[0].title===item.title){
      endpoints.push(...children[0].endpoints);
      children=children[0].children;
    }
    return {id,title:item.title,children,endpoints};
  }
  const tree=(definition as Definition[]).map(n=>convert(n)).filter(n=>n.children.length||n.endpoints.length);
  const remaining=catalog.filter(e=>!used.has(e.id));
  if(remaining.length) tree.push({id:'/imported',title:'Imported APIs',endpoints:[],children:[...new Set(remaining.map(e=>e.category))].map((category,i)=>({id:'/imported/'+i,title:category,children:[],endpoints:remaining.filter(e=>e.category===category)}))});
  return tree;
}

export function endpointGroups(tree:ApiNavigation[]):EndpointGroup[] {
  const groups:EndpointGroup[]=[];
  function walk(n:ApiNavigation,parents:string[]=[]){
    const titles=parents.at(-1)===n.title?parents:[...parents,n.title];
    if(n.endpoints.length)groups.push({id:n.id,label:titles.join(' / '),endpoints:n.endpoints});
    n.children.forEach(c=>walk(c,titles));
  }
  tree.forEach(n=>walk(n));
  return groups;
}

export function containsEndpoint(node:ApiNavigation,id:string):boolean {
  return node.endpoints.some(e=>e.id===id)||node.children.some(c=>containsEndpoint(c,id));
}
