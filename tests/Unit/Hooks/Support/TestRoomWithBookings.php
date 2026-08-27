<?php
namespace Admidio\Tests\Unit\Hooks\Support;

/** A room that removes its bookings the way the Admidio entities remove their dependent records. */
class TestRoomWithBookings extends TestRoom
{
    public function delete(): bool
    {
        $this->db->startTransaction();
        $this->deleteDependentRecords(
            new TestBooking($this->db),
            array('bok_id'),
            'bok_room_id = ?',
            array($this->getValue('room_id'))
        );
        $returnValue = parent::delete();
        $this->db->endTransaction();

        return $returnValue;
    }
}
