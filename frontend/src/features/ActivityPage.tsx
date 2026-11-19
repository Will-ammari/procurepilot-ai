import {useState} from 'react';
import {useQuery} from '@tanstack/react-query';
import {Activity,ShieldCheck} from 'lucide-react';
import {useAuth} from '../app/App';
import {date,label} from '../domain/format';
import {repo} from '../services/repository';
import {Empty,ErrorState,Loading,PageHeading,Panel,SearchInput,Table} from '../components/UI';
export function ActivityPage(){const {user}=useAuth();const [search,setSearch]=useState('');const query=useQuery({queryKey:['activity',user.id],queryFn:()=>repo.activities(user),retry:false});return <><PageHeading eyebrow="AUDIT & VISIBILITY" title="Activity log" description="A time-ordered view of procurement events and decisions."/><Panel title="Event timeline" subtitle="Organisation-scoped audit records"><div className="list-toolbar"><SearchInput value={search} onChange={setSearch} placeholder="Find events…"/><span className="list-count"><ShieldCheck size={16}/> Access controlled</span></div>{query.isPending?<Loading/>:query.isError?<ErrorState error={query.error} onRetry={()=>query.refetch()}/>:query.data.data.filter(a=>a.event.toLowerCase().includes(search.toLowerCase())).length?<Table head={['EVENT','ACTOR','SUBJECT','DATE']}>
 {query.data.data.filter(a=>a.event.toLowerCase().includes(search.toLowerCase())).map(a=><tr key={a.id}><td className="weight-medium"><span className="event-icon"><Activity size={15}/></span>{label(a.event.replaceAll('.',' '))}</td><td>{a.user?.name||`User #${a.user_id}`}</td><td className="cell-muted">{a.subject_type.split('\\').pop()} #{a.subject_id}</td><td className="cell-muted">{date(a.created_at)}</td></tr>)}</Table>:<Empty title="No matching events" detail="Try a different search. Events are written by the underlying workflow."/>}</Panel></>}
