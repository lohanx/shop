<?php
namespace app\contribute\controller\admin\connector;

use app\contribute\controller\Common;
use app\contribute\controller\AdminCheck;

use app\contribute\model\Connector;
use app\contribute\model\Book;
use app\contribute\model\BookSection;
use app\contribute\model\TagRelation;

use think\Request;
use think\Db;

use yuedu\PushBook;

class Yuedu extends Common
{

	use AdminCheck;

	public function _initialize(){

		$this->check();

	}

	const className = "yuedu";

	const secretKey = "WY9eCCjtMpfPTVyO";
	const consumerKey = "02430617";

	const bookPushAdd = 'http://testapi.yuedu.163.com/book/add.json';
	const chapterPushAdd = 'http://testapi.yuedu.163.com/bookSection/add.json';
	const bookPushUpdate = 'http://testapi.yuedu.163.com/book/update.json';
	const chapterPushUpdate = 'http://testapi.yuedu.163.com/bookSection/update.json';

	public $category = [
		4	=> [1,"玄幻"],
		5	=> [2,"科幻"],
		6	=> [3,"军事"],
		7	=> [4,"官场"],
		8	=> [5,"悬疑"],
		3	=> [7,"都市"],
		11	=> [11,"历史"],
		13	=> [8,"现言"],
		14	=> [9,"穿越"],
		15	=> [13,"仙侠"],
		9	=> [6,"灵异"],
		10	=> [10,"同人"],
		12	=> [12,"短篇"],
		16	=> [17,"种田"],
		17	=> [18,"宫斗"],
		18	=> [22,"校园"],
		19	=> [24,"古言"],
	];

	public $return = [
		'code' 	=> 200,
	];

	public function book(Connector $connector,Book $book)
	{
		$count = $book->bookCount(['check'=>1]);
		
		return $this->fetch("",[
			'count'	=> $count,
		]);
	}

	public function list(Connector $connector,Book $book,Request $request)
	{
		$check 	= $connector->getConnectorBook(Yuedu::className);
		$book 	= $book->bookCheck([],$request->get('size'));
		
		echo $this->fetch("",[
			'check' => $check,
			'list' 	=> $book,
		]);
	}

	public function add(Connector $connector,Book $book,Request $request)
	{
		$ids = explode("," , $request->post('ids'));

		$connectorBook = explode( 
			",",
			objectFormList($connector->getConnectorBook(Yuedu::className),'book_id')
		);

		$ids = array_filter( array_diff($ids,$connectorBook) );

		if( empty($ids) )
		{
			return true;
		}

		return $connector->createRelation($ids,Yuedu::className,"push");

	}

	public function delete(Connector $connector,Request $request)
	{
		$id = $request->post('id');

		return $connector->where(['book_id'=>$id,'source_name'=>Yuedu::className])->delete();
	}

	//添加推送
	public function push(Connector $connector,Book $book,BookSection $chapter,Request $request,PushBook $pushBook)
	{
		$ids = $request->post("id");

		$pushBook->key( Yuedu::secretKey,Yuedu::consumerKey );

		$pushBook->config([
			'book'	=> Yuedu::bookPushAdd,
			'chapter'	=> Yuedu::chapterPushAdd,
		]);

		$books = $book->all($ids);

		foreach($books as $book)
		{
			$tags = (new TagRelation)->getRelationTag($book->id);
			if($tags)
			{
				$book->setAttr('tags',implode(",",array_column($tags, "name")));
			}
			$book->setAttr('category_id', $this->category[$book->category_id][0] );

			$isPush = $pushBook->book($book);
			if($isPush['code'] !== 200 )
			{
				//$this->return['code'] 	= $isPush['code'];
				//$this->return['msg'][] 	= $book->title . " error :" . $isPush['error_msg'];
			}

			//章节处理
			$chapters = $book->bookSections;
			foreach($chapters as $chapter )
			{
				$chapter->content = strip_tags($chapter->content,"<p>");
				$isPush = $pushBook->chapter($chapter);
				if($isPush['code'] !== 200 )
				{
					//$this->return['code'] 	= $isPush['code'];
					//$this->return['msg'][] 	= $chapter->title . " error :" . $isPush['error_msg'];
				}
			}


		}
		
		return $this->return;
	}

	//更新推送
	public function update(Connector $connector,Book $book,BookSection $chapter,Request $request,PushBook $pushBook)
	{
		$ids = $request->post("id");

		$pushBook->key( Yuedu::secretKey,Yuedu::consumerKey );
		$pushBook->config([
			'updateBook'	=> Yuedu::bookPushUpdate,
			'updateChapter'	=> Yuedu::chapterPushUpdate,
		]);

		$books = $book->all($ids);

		foreach($books as $book)
		{
			$tags = (new TagRelation)->getRelationTag($book->id);
			if($tags)
			{
				$book->setAttr('tags',implode(",",array_column($tags, "name")));
			}
			$book->setAttr('category_id', $this->category[$book->category_id][0] );

			$isPush = $pushBook->updateBook($book);
			if($isPush['code'] !== 200 )
			{
				$this->return['code'] 	= $isPush['code'];
				$this->return['msg'][] 	= $book->title . " error :" . $isPush['error_msg'];
			}

			//章节处理
			$chapters = $book->bookSections;
			foreach($chapters as $chapter )
			{
				$chapter->content = strip_tags($chapter->content,"<p>");
				$isPush = $pushBook->updateChapter($chapter);
				if($isPush['code'] !== 200 )
				{
					$this->return['code'] 	= $isPush['code'];
					$this->return['msg'][] 	= $chapter->title . " error :" . $isPush['error_msg'];
				}
			}


		}
		
		return $this->return;
	}


	//添加推送
	public function pushAdd(Connector $connector,Book $book,BookSection $chapter,Request $request,PushBook $pushBook)
	{
		$ids 	= explode( 
			",",
			objectFormList($connector->getConnectorBook(Yuedu::className),'book_id')
		);

		$pushBook->key( Yuedu::secretKey,Yuedu::consumerKey );
		$pushBook->config([
			'chapter'	=> Yuedu::chapterPushAdd,
		]);

		$books = $book->all($ids);

		foreach($books as $book)
		{
			//章节处理
			$chapters = $book->bookSections;
			foreach ($chapters as $key => $row) {
			    $sort[$key]  = $row['sort'];
			}
			if($chapters)
			{
				array_multisort($sort, SORT_DESC , $chapters);	
			}

			foreach($chapters as $chapter )
			{
				$chapter->content = strip_tags($chapter->content,"<p>");
				$isPush = $pushBook->chapter($chapter);
				
				if($isPush['code'] !== 200 )
				{
					break;
				}
			}


		}

		return $this->return;
	}


}