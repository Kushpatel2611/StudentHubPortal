# StudentHub ER Model — Practical 8

```text
STUDENTS                         REGISTRATIONS                         EVENTS
---------                        ------------                         ------
PK student_id  1 -----------<    PK registration_id    >----------- 1  PK event_id
   student_code                 FK student_id                         title
   full_name                    FK event_id                           description
   email                        registration_date                     event_date
   course                       status                                venue
                                                                      capacity
```

- One student can have many registrations.
- One event can have many registrations.
- Students and events have a many-to-many relationship, resolved by `registrations`.
- `student_code` and `email` are unique.
- The `(student_id, event_id)` pair is unique to prevent duplicate registration.
- All tables use InnoDB to support foreign keys and transactions.
