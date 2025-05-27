<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $users=User::all();
        return response()->json(['data' => $users]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'email' => 'required|string|email|max:100|unique:users,email',
            'password' => 'required|string|min:2|max:100', 
            'permissions' => 'required', 
        ]);
        
        if ($validator->fails()) {
            return response()->json(['status' => 'error','message' => $validator->errors()->first()],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }
        
        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
                'permissions' => $request->input('permissions'),
            ]);

            if(!$user){
                DB::rollBack();
                return response()->json(['status' => 'error','message' => 'Something Went Wrong Please Try Again Later.'], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
            }

            DB::commit();
            return response()->json(['status' => 'success','message' => 'User Created Successfully.'], Response::HTTP_CREATED); // 201 Created
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error','message' => 'Failed to Create User. ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            $user = User::where('id',$id)->firstOrFail();
            return response()->json(['data' => $user]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'User Not Found.'], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
 
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'email' => 'required|string|email|max:100|unique:users,email,' . $id,
            'password' => 'nullable|string|min:2|max:100',
            'permissions' => 'required|json', 
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error','message' => $validator->errors()->first()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            DB::beginTransaction();
            $user = User::find($id);
            if (!$user) {
                DB::rollBack();
                return response()->json(['status' => 'error','message' => 'User not found.'], Response::HTTP_NOT_FOUND);
            }

            $updateData = [
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'permissions' => $request->input('permissions'),
            ];

            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->input('password'));
            }

            $updated = $user->update($updateData);

            if (!$updated) {
                DB::rollBack();
                return response()->json(['status' => 'error','message' => 'Something Went Wrong Please Try Again Later.'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            DB::commit();
            
            // Return updated user data (excluding sensitive fields)
            return response()->json([
                'status' => 'success',
                'message' => 'User Updated Successfully.',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'permissions' => $user->permissions,
                    'updated_at' => $user->updated_at
                ]
            ], Response::HTTP_OK);
            
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error','message' => 'Failed to Update User. ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    public function toggleStatus(User $user)
    {
        if ($user->id === 1) {
            return response()->json(['status' => 'error','message' => 'This user cannot be deactivated.'], 403);
        }

        if($user->status === 'active'){
            $user->tokens()->delete();
            $user->status = 'deactivate';
        }else{
            $user->status = 'active';
        }

        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'User status updated successfully.',
            'account_status' => $user->status
        ]);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    // public function destroy($id)
    // {
    //     try {
    //         $resource = User::where('id',$id)->firstOrFail();
    //         $resource->delete();
    //         return response()->json(['status'=>'success','message' => 'User Deleted Successfully']);
    //     } catch (ModelNotFoundException $e) {
    //         return response()->json(['status'=>'error', 'message' => 'User Not Found.'], Response::HTTP_NOT_FOUND);
    //     } catch (Exception $e) {
    //         return response()->json(['status'=>'error', 'message' => 'Something went wrong.'], Response::HTTP_INTERNAL_SERVER_ERROR);
    //     } 
    // }
}
