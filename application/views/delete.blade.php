<div class="modal hide fade" id="delete_{{ $id }}" style="width: 300px; margin-left: -150px">
  <div class="modal-header">
    <button type="button" class="close" data-dismiss="modal">×</button>
    <h3>{{ $identifier }}<br />sistemden silinecek</h3>
  </div>
  <div class="modal-body">
  	<p>Bu işlem geri alınamaz!</p>
  </div>
  <div class="modal-footer">
    <a href="#" class="btn" data-dismiss="modal">Vazgeç</a>
    <a href="{{ $delete_link }}" class="btn btn-primary">Sil</a>
  </div>
</div>