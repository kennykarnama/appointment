<?php

namespace Appointment;

use function Appointment\createDateRFC;
use function Appointment\filterDate;
use function Appointment\filterKeyOnArr;
use function Appointment\getDayFromDate;
use function Appointment\getIntervalBetweenTime;
use function Appointment\getTimeFromDate;

/**
 *
 */
class SlotHandler
{
    const DEFAULT_DURATION = 60;
    /**
     * Get available slots
     * @param  string $dateSelected
     * @param  Appointment\Attendee $attendee
     * @param  int $duration
     * @return array
     */
    public function getAvailableSlots(
        $dateSelected,
        $attendee,
        $duration = self::DEFAULT_DURATION
    ) {
        $slotOnConfig = $attendee->getConfig()->getDateSlots();

        if (empty($slotOnConfig)) {
            return [];
        }
        $timelines        = [];
        $eventsConstraint = array_map(
            function ($time) use ($dateSelected, &$timelines) {
                $timelines[] = array(
                    'start' => $time,
                    'end'   => $time,
                );
                return createDateRFC($dateSelected . " " . $time);
            },
            $this->getTimeSlot(
                $this->getSlotAvailableOnConfig(
                    $dateSelected,
                    $slotOnConfig
                )
            )
        );
        $eventsOnCalendar = $this->getBookedSlotsFromEvents(
            $attendee->makeRequest(
                $attendee::FETCH_LIST_EVENTS,
                $optParams = array(
                    'orderBy'      => 'startTime',
                    'singleEvents' => true,
                    'timeMin'      => filterKeyOnArr($eventsConstraint, 'start'),
                    'timeMax'      => filterKeyOnArr($eventsConstraint, 'end'),
                )
            )
        );
        array_unshift($eventsOnCalendar, array_shift($timelines));
        array_push($eventsOnCalendar, array_shift($timelines));
        $slotsAvailable = [];
        for ($i = 1; $i < count($eventsOnCalendar); $i++) {
            $slotsAvailable[] = $this->createAvailSlot(
                $eventsOnCalendar[$i - 1]['end'],
                $eventsOnCalendar[$i]['start'],
                $dateSelected,
                $duration
            );
        }
        return $slotsAvailable;
    }

    /**
     * mapping start & end dates
     * from events object
     * @param  array $events
     * @return array
     */
    public function getBookedSlotsFromEvents($events)
    {
        $result = array_map(function ($event) {
            $startTime = getTimeFromDate(
                filterDate(
                    date_create($event->getStart()->dateTime)
                )
            );
            $endTime = getTimeFromDate(
                filterDate(
                    date_create($event->getEnd()->dateTime)
                )
            );
            return [
                'start' => $startTime,
                'end'   => $endTime,
            ];
        }, $events);

        return $result;
    }
    /**
     * Create slot availaible
     * @param  string $before
     * @param  string $after
     * @param  string $dateSelected
     * @param  int $duration
     * @return array
     */
    private function createAvailSlot($before, $after, $dateSelected, $duration)
    {
        $interval = getIntervalBetweenTime($before, $after);
        if ($interval >= $duration) {
            return array(
                'start' => createDateRFC($dateSelected . ' ' . $before),
                'end'   => createDateRFC($dateSelected . ' ' . $after),
            );
        }
        return [];
    }
    /**
     * getSlotAvailableOnConfig
     * @param  string $date
     * @param  array  $config
     * @return array
     */
    private function getSlotAvailableOnConfig($date, $config)
    {
        $day    = getDayFromDate($date);
        $result = array_column($config, $day);
        return array_shift($result);
    }
    /**
     * getTimeSlot
     * @param  string $slot ex. 10:00 - 11:00
     * @return array
     */
    private function getTimeSlot($slot)
    {
        $times  = explode('-', $slot);
        $result = array_map(function ($time) {
            return trim($time);
        }, $times);
        return array_combine(["start", "end"], $result);
    }
}
