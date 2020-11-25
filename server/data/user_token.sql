truncate teable 



drop procedure if exists user_token;
delimiter $$
create procedure user_token( _remember_token varchar(1000)) 
begin
select id,name from users where  remember_token=_remember_token;
end
$$
delimiter ;

-- 用户外汇列表 

drop procedure if exists get_user_for_count;
delimiter $$
create procedure get_user_for_count(_id int(11)) 
begin
select count(*) as total_count from user_foreign_exchange_lists where user_id=_id;
end
$$
delimiter ;

drop procedure if exists user_for_list;
delimiter $$
create procedure user_for_list(_user_id int(11)) 
begin

select b.* from user_foreign_exchange_lists a
left join foreign_exchange_lists b 
on a.FS = b.FS
where a.user_id =_user_id;
end
$$
delimiter ;
-- 用户持仓
drop procedure if exists get_user_order_count;
delimiter $$
create procedure get_user_order_count(_id int(11)) 
begin
select count(*) as total_count from orders where user_id=_id && status!=2;
end
$$
delimiter ;

drop procedure if exists user_order_list;
delimiter $$
create procedure user_order_list(_user_id int(11),_field char(50),_sort char(50)) 
begin
if _sort='desc' then
select a.*,b.B1,b.S1 from orders a
left join foreign_exchange_lists b 
on a.FS = b.FS
where a.user_id =_user_id && status!=2 order by  (case _field   
         when 'created_at' then a.created_at
					when 'FS' then a.FS
					when 'profit' then a.profit
         else a.id end) desc;
else
select a.*,b.B1,b.S1 from orders a
left join foreign_exchange_lists b 
on a.FS = b.FS
where a.user_id =_user_id && status!=2 order by  (case _field   
         when 'created_at' then a.created_at
					when 'FS' then a.FS
					when 'profit' then a.profit
         else a.id end) asc;
end if;
end
$$
delimiter ;

drop procedure if exists user_info;
delimiter $$
create procedure user_info(_user_id int(11))
begin
select a.id,
a.name,
a.phone,
a.last_balance,
(a.balance+IFNULL(sum(b.profit),0)) as balance,
IFNULL(sum(b.profit),0) as profit,
if(advance=0,0,((a.balance+IFNULL(sum(b.profit),0))/advance)*100) as advancebl,
a.advance,
a.frozen_balance 
from users a 
left join orders b on a.id = b.user_id && (b.status=0 || b.status=1) where a.id =_user_id;
end
$$
delimiter ;


drop procedure if exists get_one_for_list;
delimiter $$
create procedure get_one_for_list(_fs char(50))
BEGIN
select * from foreign_exchange_lists where FS=_fs;
end 
$$
delimiter ;



