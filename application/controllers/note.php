<?php

class Note_Controller extends Base_Controller
{
    public function action_info($uniqueid)
    {
        $note = Note::where('uniqueid', '=', $uniqueid)->first();
        $data = array('note' => '', 'info' => '');
        if ($note) {
            $data['note'] .= $note->note;
            $data['info'] .= $note->created_at . ' tarihinde ' . User::find($note->created_by)->username . ' tarafından oluşturuldu.';
            if ($note->updated_by) {
                $data['info'] .= '<br>' . $note->updated_at . ' tarihinde ' . User::find($note->updated_by)->username . ' tarafından düzenlendi.';
            }
        }
        return json_encode($data);
    }

    public function action_edit($uniqueid)
    {
        $response = array();
        if(Input::get('note')) {
            $note = Note::where('uniqueid', '=', $uniqueid)->first();
            if ($note) {
                $note->note = Input::get('note');
                $note->updated_by = Auth::user()->id;
                $note->save();
                $response['alert'] = 'success';
                $response['message'] = 'Not düzenlendi.';
            } else {
                $response['alert'] = 'danger';
                $response['message'] = 'uniqueid geçersiz!';
            }
        } else {
            $response['alert'] = 'danger';
            $response['message'] = 'Not boş bırakılamaz!';
        }
        return json_encode($response);
    }

    public function action_add()
    {
        $response = array();
        if(Input::get('note') && Input::get('uniqueid')) {
            $note = new Note();
            $note->uniqueid = Input::get('uniqueid');
            $note->note = Input::get('note');
            $note->created_by = Auth::user()->id;
            $note->save();
            $response['alert'] = 'success';
            $response['message'] = 'Not eklendi.';
        } elseif (Input::get('uniqueid')) {
            $response['alert'] = 'danger';
            $response['message'] = 'Not boş bırakılamaz!';
        } else {
            $response['alert'] = 'danger';
            $response['message'] = 'uniqueid tanımlı değil!';
        }
        return json_encode($response);
    }

    public function action_delete()
    {
        $response = array();
        if(Input::get('uniqueid')) {
            $note = Note::where('uniqueid', '=', Input::get('uniqueid'))->first();
            if ($note) {
                $note->delete();
                $response['alert'] = 'success';
                $response['message'] = 'Not silindi.';
            } else {
                $response['alert'] = 'danger';
                $response['message'] = 'Geçersiz uniqueid!';
            }
        } else {
            $response['alert'] = 'danger';
            $response['message'] = 'uniqueid tanımlı değil!';
        }
        return json_encode($response);
    }
}
