<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Product;
use App\Models\PostCate;
use App\Models\Post;

class PostCateController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }
    public function index(Request $request)
    {
        $query = PostCate::whereNull('deleted_at');
        if (!empty($request->search)) {
            $query->where('post_name', 'like', '%' . $request->search . '%');
        }
        $post_cate = $query->paginate(10);
        $data = [
            'rows' => $post_cate,
            'breadcrumbs'        => [
                [
                    'name' => 'Danh mục bài viết',
                    // 'url'  => 'admin/dashboard',
                ],
            ],
            'isPost' => true,
        ];

        return view('admin.post_cate.index', $data);
    }

    public function store(Request $request)
    {
        if (empty($request->id_PostCate)) {
            $PostCate_check = PostCate::where('post_name', $request->name_PostCate)->whereNull('deleted_at')->get();
            if (count($PostCate_check) > 0) {
                return redirect()->back()->with('error', 'Đã có tên danh mục');
            }

            $post_cate = new PostCate();
            $post_cate->post_name = $request->name_PostCate;
            $post_cate->post_path = $request->path_PostCate;
            $post_cate->save();
            return redirect()->route('admin.post_cate.index')->with('success', 'Tạo danh mục thành công');
        }
        $post_cate = PostCate::find($request->id_PostCate);
        if (empty($post_cate)) {
            return redirect()->route('admin.post_cate.index')->with('error', 'Không tìm thấy danh mục');
        }

        $PostCate_check = PostCate::where('post_name', $request->name_PostCate)->where('id', '<>', $request->id_PostCate)->whereNull('deleted_at')->get();
        if (count($PostCate_check) > 0) {
            return redirect()->back()->with('error', 'Đã có tên danh mục');
        }

        $post_cate->post_name = $request->name_PostCate;
        $post_cate->post_path = $request->path_PostCate;
        $post_cate->save();
        return redirect()->route('admin.post_cate.index')->with('success', 'Cập nhật danh mục thành công');
    }

    public function delete(Request $request)
    {
        // dd(json_decode($request->list_id));
        $flag = false;
        $list_id = json_decode($request->list_id);
        foreach ($list_id as $id) {
            $post_cate = PostCate::find($id);
            if (!empty($post_cate)) {
                if (count(Post::where('post_cate_id', $post_cate->id)->whereNull('deleted_at')->get()) != 0) {
                    return redirect()->back()->with('error', 'Danh mục ' . $post_cate->post_name . ' đã có bài viết');
                }
            }
        }

        foreach ($list_id as $id) {
            $post_cate = PostCate::find($id);
            if (!empty($post_cate)) {
                $post_cate->forceDelete();
            }
        }
        return redirect()->back()->with('success', 'Xóa danh mục thành công');
    }
}
