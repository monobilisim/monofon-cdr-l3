<?php

class V_Cdr {

    /**
     * Make changes to the database.
     *
     * @return void
     */
    public function up()
    {
        return;

        /* bu migration sunucu üzerinde yapılmalı çünkü cdrapp kullanıcısına sadece select yetkisi veriyoruz

        CREATE OR REPLACE VIEW v_cdr AS
            SELECT
                calldate,
                uniqueid,
                IF(cnum != '', cnum, src) AS src,
                dst,
                cnum,
                channel,
                duration,
                billsec,
                disposition,
                recordingfile,
                linkedid
            FROM cdr;

        */
    }

    /**
     * Revert the changes to the database.
     *
     * @return void
     */
    public function down()
    {
        //
    }

}