# Review fixes: F12 day-of-service

Branch `review/f12-day-of-service-fixes`, from `develop` at `4a758cc`. Source: the `/code-review` of F12 (`a684388..4a758cc`) against `docs/features/12-day-of-service`. F01 to F11 were not reviewed line by line.

## Findings and outcome

| # | Finding | Outcome | Where |
| --- | --- | --- | --- |
| 1 | Extend displaced neighbours of any status (HELD, CHECKED_IN, IN_PROGRESS lost all allocations) | Fixed. Only a `CONFIRMED` neighbour is displaced. Any other neighbour that cannot move fails the extend with 409 `NO_AVAILABILITY` `SLOT_TAKEN`. A moved `HELD` neighbour gets no SMS. | `Reassigner::displace`, TC-12-29 |
| 2 | Extend did not release expired holds first | Fixed. `releaseBlocking` runs on `[old end, new end)` before the neighbour read. | `WorkshopService::extend`, TC-12-30 |
| 3 | A deadlock during extend gave a 500 | Fixed. `DeadlockException` and 40P01/40001 retry once, like 23P01, then 409. | `WorkshopService::extend`, TC-12-31 |
| 4 | Board drew `EXPIRED` appointments and counted them in `bookedMinutes` | Fixed in SQL: `listForBoard` returns only board statuses and live holds. | `AppointmentRepository::listForBoard`, TC-12-32 |
| 5 | `nextBooking` ignored status; N+1 query | Fixed. Earliest later booking that holds a slot, or a cancelled one only when nothing live follows. Computed from the day's list, with no query per alert. | `WorkshopService::findNextBooking`, TC-12-33 |
| 6 | Contact banner (WEB §B3) never shown | Fixed. `ContactRequiredBanner` shows the reason, note, phone, and Reschedule/Cancel buttons for advisors. | TC-12-34 |
| 7 | Action errors were swallowed; F12 codes missing from `errorCopy` | Fixed. `describeActionError`: 412 opens `AppointmentModifiedAlert` with a reload, 409 shows `actions.extendFailed`, any other code shows its README §5 sentence. Keys now settle with the answer; before, `settle()` was called with no argument and always kept the key. | TC-12-34, TC-12-35 |
| 8 | Board "Extend 30 min" handled only 412 | Fixed. Other errors are shown on the alert card. | TC-12-35 |
| 9 | One extend key shared by all durations | Fixed. New seam `useIdempotencyKeys(input).keyFor(minutes)` (README §6). | TC-12-36 |
| 10 | Eloquent queries in `WorkshopService` | Fixed. The board reads through `AppointmentRepositoryInterface` and `MasterDataRepositoryInterface`. | — |
| extra | Hex colours and hard-coded text on the board | Fixed. Theme CSS variables are used; labels come from `copy.ts` and `shared/appointments/status.ts`. | — |

## Spec deviations (recorded in the spec)

- D-12.9: only `CONFIRMED` is displaced. Other statuses fail the extend. **User-visible: please confirm.**
- D-12.4: expired holds are released first, and deadlocks are retried.
- D-12.13: `EXPIRED` is hidden from the board, and the `nextBooking` rule is now stated.
- New copy that the spec did not have: `actions.extendFailed`, `board.legendLabel`, `board.displacedColumns`, `board.bookedMinutes`. **Please check the wording.**
- 6-test-case M5 said PASS, but the banner was missing. The note is corrected; M5 has not been re-run by hand.

## Not done

- Four older screens (F07/F09 cancel and reschedule, staff and customer) still parse a 412 body inline. `modifiedConflict()` could replace that code; this was left out to keep the change inside F12.
- The board response has no row limit (D-G.12). It is one dealership-day.
- Menu reopening cannot be tested in jsdom, because Mantine never renders the dropdown a second time. Keys per duration are therefore tested on the hook (TC-12-36), not by clicking the menu twice.

## Checks

`php artisan test`: 677 passed. Pint clean, PHPStan clean, `npm run lint` clean, `npm run test`: 229 passed. The 4 new extend tests were confirmed to fail on the old code.
