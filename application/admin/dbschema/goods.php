<?php
$db['goods'] = [
	'columns'=>[
		'goods_id'=>[
			'type'=>'number',
			'autoincrement'=>true,
			'required'=>true,
			'comment'=>'自增ID',
		],
		'names'=>[
			'type'=>'string',
			'required'=>true,
			'comment'=>'商品名称'
		],
		'status'=>[
			'type'=>array(
				'0',
				'1',
			),
			'default'=>'0',
			'is_multiselect'=>true,
			'comment'=>'状态',
		],
		'info'=>[
			'type'=>'text',
			'comment'=>'简介'
		],
		'price'=>[
			'type'=>'decimal(20,3)',
			'required'=>true,
			'comment'=>'商品价格',
		],
		'sale_price'=>[
			'type'=>'decimal(20,3)',
			'required'=>true,
			'comment'=>'商品销售价格',
		],
		'test_price'=>[
			'type'=>'decimal(20,3)',
			'required'=>true,
			'comment'=>'测试价格',
		],
	],
	'primary'=>'goods_id',
	'index'=>[
		'index_names'=>['columns'=>'names'],
		'index_status'=>['columns'=>'status'],
		'index_price'=>['columns'=>'price'],
	],
	'comment'=>'商品表',
];
