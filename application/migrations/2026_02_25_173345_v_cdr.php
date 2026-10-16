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
                clid,
                IF(cnum != '', cnum, src) AS src,
                dst,
                dcontext,
                channel,
                dstchannel,
                lastapp,
                lastdata,
                duration,
                billsec,
                disposition,
                uniqueid,
                linkedid,
                recordingfile,
                cnum,
                cnam
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