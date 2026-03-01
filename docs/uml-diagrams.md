# LMS Project UML Diagrams

## 1) Use Case Diagram
```mermaid
flowchart LR
    Student([Student])
    Faculty([Faculty])
    Admin([Admin])

    subgraph LMS[Learning Management System]
      UC1((Register / Login))
      UC2((Manage Profile))
      UC3((Browse Courses))
      UC4((Enroll / Unenroll Course))
      UC5((View Course Dashboard))
      UC6((Take Assessment))
      UC7((Grade Submissions))
      UC8((View Transcript & Certificate))
      UC9((Submit Teacher Evaluation))
      UC10((View Faculty Ratings))
      UC11((Manage Eval Questions))
      UC12((Send/Read Messages))
      UC13((View Notifications))
      UC14((Create Events))
      UC15((Post Announcements))
      UC16((Manage Users & Roles))
      UC17((View Admin Reports))
    end

    Student --> UC1
    Student --> UC2
    Student --> UC3
    Student --> UC4
    Student --> UC5
    Student --> UC6
    Student --> UC8
    Student --> UC9
    Student --> UC12
    Student --> UC13

    Faculty --> UC1
    Faculty --> UC2
    Faculty --> UC5
    Faculty --> UC7
    Faculty --> UC10
    Faculty --> UC12
    Faculty --> UC13
    Faculty --> UC14
    Faculty --> UC15

    Admin --> UC1
    Admin --> UC11
    Admin --> UC16
    Admin --> UC17
    Admin --> UC9
```

## 2) Data Flow Diagram
```mermaid
flowchart LR
    U[Users: Student/Faculty/Admin]
    FE[Frontend Pages + app.js]
    API["PHP API Endpoints (/src/api)"]
    C[Controllers]
    S[Services]
    R[Repositories]
    DB[(MySQL lms DB)]

    U -->|HTTP + Session Cookie| FE
    FE -->|"fetch /api/*.php JSON"| API
    API -->|"body(), require_login(), require_role()"| C
    C -->|delegate business operations| S
    S -->|read/write queries| R
    R --> DB
    DB --> R
    R --> S
    S -->|"ok, data, error tuple"| C
    C --> API
    API -->|json_response| FE
    FE --> U

    S -->|notification events| R
```

## 3) Activity Diagram (Student Takes Assessment)
```mermaid
flowchart TD
    A([Start]) --> B[Student opens Assessments page]
    B --> C[Frontend requests assessments_list_for_student.php]
    C --> D{Authenticated student?}
    D -- No --> Z[Return Unauthorized]
    D -- Yes --> E[List published assessments in enrolled courses]
    E --> F[Student selects assessment]
    F --> G[Load questions via assessments_detail.php]
    G --> H[Student answers questions]
    H --> I[Submit via assessments_submit.php]
    I --> J{Current time within open_at/close_at?}
    J -- No --> K["Return: Assessment window closed"]
    J -- Yes --> L[Upsert submission]
    L --> M[Auto-grade MCQ answers]
    M --> N[Store submission_answers + score]
    N --> O[Return submission_id and auto_score]
    O --> P([End])

    Z --> P
    K --> P
```

## 4) Class Diagram (Architecture)
```mermaid
classDiagram
    class AuthController
    class CourseController
    class AssessmentController
    class EvaluationController
    class CommunicationController
    class EventController
    class UserController

    class AuthService
    class CourseService
    class AssessmentService
    class EvaluationService
    class CommunicationService
    class EventService
    class UserService

    class UserRepository
    class CourseRepository
    class AssessmentRepository
    class EvaluationRepository
    class CommunicationRepository
    class EventRepository

    class DB

    class User
    class Course
    class Assessment
    class Evaluation

    AuthController --> AuthService
    CourseController --> CourseService
    AssessmentController --> AssessmentService
    EvaluationController --> EvaluationService
    CommunicationController --> CommunicationService
    EventController --> EventService
    UserController --> UserService

    AuthService --> UserRepository
    UserService --> UserRepository

    CourseService --> CourseRepository
    CourseService --> EventRepository
    CourseService --> CommunicationRepository

    AssessmentService --> AssessmentRepository
    AssessmentService --> CommunicationRepository

    EvaluationService --> EvaluationRepository
    EvaluationService --> CourseRepository
    EvaluationService --> CommunicationRepository

    CommunicationService --> CommunicationRepository
    CommunicationService --> CourseRepository

    EventService --> EventRepository

    UserRepository --> DB
    CourseRepository --> DB
    AssessmentRepository --> DB
    EvaluationRepository --> DB
    CommunicationRepository --> DB
    EventRepository --> DB

    UserRepository ..> User
    CourseRepository ..> Course
    AssessmentRepository ..> Assessment
    EvaluationRepository ..> Evaluation
```

## 5) Sequence Diagram (Submit Teacher Evaluation)
```mermaid
sequenceDiagram
    autonumber
    actor Student
    participant FE as Frontend (evaluate_teacher.html)
    participant API as eval_submit.php
    participant Ctrl as EvaluationController
    participant Svc as EvaluationService
    participant EvalRepo as EvaluationRepository
    participant CommRepo as CommunicationRepository
    participant DB as MySQL

    Student->>FE: Fill ratings/comments + submit
    FE->>API: POST /api/eval_submit.php (course_id, faculty_id, answers[])
    API->>API: require_role([admin,student,faculty])
    API->>Ctrl: submit(body, current_user)
    Ctrl->>Svc: submitEvaluation(courseId, facultyId, evaluatorId, role, answers)
    Svc->>EvalRepo: existingEvaluation(courseId, facultyId, evaluatorId)
    EvalRepo->>DB: SELECT evaluations ...
    DB-->>EvalRepo: none/row

    alt Already evaluated
      EvalRepo-->>Svc: existing row
      Svc-->>Ctrl: [false, null, "Already evaluated"]
    else First evaluation
      EvalRepo-->>Svc: none
      Svc->>EvalRepo: createEvaluation(...)
      EvalRepo->>DB: INSERT evaluations
      DB-->>EvalRepo: evalId
      loop each answer
        Svc->>EvalRepo: addAnswer(evalId, question_id, rating, comment)
        EvalRepo->>DB: INSERT evaluation_answers
      end
      Svc->>CommRepo: notify(facultyId, evaluation_published, evalId, ...)
      CommRepo->>DB: INSERT notifications
      Svc-->>Ctrl: [true, {id: evalId}, null]
    end

    Ctrl-->>API: tuple
    API-->>FE: json_response(ok, data, error)
    FE-->>Student: Success / error message
```
