// Получаем все оплаты, у которых нет даты
$obj=$DB->Query("SELECT  payments.pk_payment, payments.date, payments.fk_user  FROM payments WHERE payments.date=0");

/**
 * Получаем данные, заодно группируем
 */
$id=0;
$group=0;
$arAllGroup=array();
$arID=array(); //сюда собрали все ID соседних полей
$arUserID=array(); //сюда собрали все ID пользователей
while($row=$obj->Fetch()){
   if($id!=($row['id']+1)){
      $arID[]=$id=0?intval($row['id']-1):($row['id']+1);
      $group++;
      $id = $row['id'];
   }
    $arAllGroup[$group][]=$row;
    $arUserID[$row['fk_user']]=$row['fk_user'];

}
/*
 * Как результат, у нас в массиве arAllGroup собранны все платежи без даты
 * А в массиве $arID все ID соседних элементов
 * А в массиве $arUserID данные о всех пользователях тех платежей, у которых дата равна нулю
 */


/**
 * получили данные о всех соседних элементах
 */
$obj=$DB->Query(
"SELECT
          `payments`.`pk_payment`, `payments`.`date`, `payments`.`fk_user`
    FROM
          `payments`
    WHERE
          `payments`.`pk_payment` IN ('".implode("','",$arID)."')");
//заполнили массив
while($row=$obj->Fetch()){
    $arPaymentsDate[$row['id']]=$row;
}

/*
 * Получили список пользователей
 */
$obj=$DB->Query(
"SELECT
          `users`.`pk_user`, `users`.`regdate `
    FROM
          `users`
    WHERE
          `users`.`pk_user` IN ('".implode(",",$arUserID)."')");

//заполнили массив
while($row=$obj->Fetch()){
    $user[$row['id']]=$row;
}

$arResult=array();

foreach($arAllGroup as $groupV=>$arPayments){
        $timeStart=0;
        $timeEnd=0;

        $startPaymentsGroup  =  $arPayments[0]; // первый элемент группы
        $endPaymentsGroup    =  end($arPayments); // последний элемент

        if(isset($arPaymentsDate[($startPaymentsGroup['id']-1)])){//если этот элемент существует, то это начало данного блока
            $timeStart=(int)$arPaymentsDate[($startPaymentsGroup['id']-1)]['date'];
        }elseif($startPaymentsGroup['id']==0){ /* скорей всего начала нет */
            //В этом случае, лучше сюда дату поставить вручную, так как это будет первый элемент и легко вручную посмотреть на первого пользователя.
            $timeStart=strtotime("2011-05-01");
        }

        $timeEnd=(int)$arPaymentsDate[($endPaymentsGroup['id']+1)]['date'];


    if($timeEnd==0){//значит этот элемент это последний элемент ставим текущую дату
        $timeEnd = time();
    }

    /**
     * итак, знаем начало и конец блока
     * Распределяем время
     * Пусть это будет равный интервал между нашими записями.
     * Плюс нужно учитывать время регистрации пользователя, что бы запись была позже его регистрации
     */

    $time = $timeStart;
    $countElGroup=count($payment);//количество элементов в группе
    $timeStep=($timeEnd-$timeStart)/$countElGroup;// распределяем равномерно по шагам промежуток

    $i=1;
    foreach($arPayments as $payment){
        $userReg = $user[$payment['fk_user']]['regdate']; //время регистрации пользователя
        $time += $timeStep;
        if($userReg>=$time){
            $time=$userReg+5;//Я прибавил 5 секунд после регистрации. Потому что человек не мог зарегистрироваться и тут же оплатить. Хотя если мог, то нужно убрать.
            // заного распределяем время
            $timeStep=(int)(($timeEnd-$time)/($countElGroup-$i));// распределяем равномерно по шагам промежуток
        }

        $arResult[]=array("pk_payment"=>(int)$payment['pk_payment'],"date"=>$time);
        $i++;
    }

}

// $arResult -  массив с ID элементами и новым временем
// Дальше все зависит от ситуации и ресурсов.

// 1 вариант
foreach($arResult as $payment){
    $obj=$DB->Query(
        "UPDATE
                payments
        SET
                payments.date = {$payment['date']}
        WHERE
                payments.pk_payment = {$payment['pk_payment']}");

}

// 2 вариант
// Сохранить эти данные куда нибудь в виде серялизоованного массива.
// Потом доставать отттуда чать и обновлять
// По сути, разбить процесс одновления на несколько этапов.

// 3 вариант, сформировать запросы, записать их в файлы(файлы) и обновить через консоль


/**
 * Итог: 3 запроса к базе данных. В худшем случае 2 массива по 10 000 элементов и один из 20 000 элементов
 * Сознательно не использовал JOIN, так как запрос разовый, и скорость не особо нужна, а ресурсов мало.
 *
 */
