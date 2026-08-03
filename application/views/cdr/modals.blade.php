<!-- Modal windows -->
<div class="modal fade" id="listen">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">×</button>
        <h3>Ses Kaydı</h3>
    </div>
    <div class="modal-body">
        <p><span class="spinner"></span></p>
    </div>
    <div class="modal-footer">
        <a href="#" class="btn" data-dismiss="modal">Kapat</a>
    </div>
</div>

<div class="modal fade" id="note-modal">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">×</button>
        <h3 id="note-modal-title">Not</h3>
    </div>
    <div class="modal-body">
        <div id="note-message" class="alert" role="alert"></div>
        <div id="note-delete-confirm-message" class="alert alert-warning hide" role="alert">Bu notu silmek istediğinizden emin misiniz?</div>
        <form data-async data-target="#note-message" method="POST" class="note-form">
            <textarea id="note-textarea" class="input-block-level" name="note" rows="5" placeholder="Lütfen not yazın."></textarea>
        </form>
        <form id="note-delete-confirm-form" data-async data-target="#note-delete-confirm-message" method="POST" class="hide">
            <button form="note-delete-confirm-form" type="submit" class="btn btn-danger" id="note-delete-confirm-yes">Evet, bu notu sil</button>
            <a href="#" class="btn" id="note-delete-confirm-no">Hayır</a>
        </form>
    </div>
    <div class="modal-footer">
        <a href="#" class="btn btn-danger pull-left hide" id="note-delete-button">Sil</a>
        <a href="#" class="btn" data-dismiss="modal">İptal</a>
        <button type="submit" class="btn btn-success note-submit-button">Kaydet</button>
    </div>
</div>
