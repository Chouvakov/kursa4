--
-- Database "cofe" dump
--

--
-- PostgreSQL database dump
--

-- Dumped from database version 17.2
-- Dumped by pg_dump version 17.2

-- Started on 2025-04-22 02:25:07

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- TOC entry 4914 (class 1262 OID 17313)
-- Name: cofe; Type: DATABASE; Schema: -; Owner: postgres
--

CREATE DATABASE cofe WITH TEMPLATE = template0 ENCODING = 'UTF8' LOCALE_PROVIDER = libc LOCALE = 'Russian_Russia.1251';


ALTER DATABASE cofe OWNER TO postgres;

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- TOC entry 237 (class 1255 OID 17567)
-- Name: delete_client(integer); Type: PROCEDURE; Schema: public; Owner: postgres
--

CREATE PROCEDURE public.delete_client(IN p_id_client integer)
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF EXISTS (SELECT 1 FROM feedback WHERE ID_client = p_id_client) THEN
        RAISE EXCEPTION 'Невозможно удалить ID %:у клиента есть отзыв.', p_id_client;
    END IF;
    DELETE FROM client
    WHERE ID_client = p_id_client;
    RAISE NOTICE 'Клиент с ID % был удален.', p_id_client;
END;
$$;


ALTER PROCEDURE public.delete_client(IN p_id_client integer) OWNER TO postgres;

--
-- TOC entry 235 (class 1255 OID 17561)
-- Name: update_table_status(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.update_table_status() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    -- Обновляем статус стола на "занят" (2) при создании нового заказа
    UPDATE Tabble
    SET status = 2  -- Статус "занят"
    WHERE ID_tabble = NEW.ID_tabble;
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.update_table_status() OWNER TO postgres;

--
-- TOC entry 236 (class 1255 OID 17563)
-- Name: update_table_status_on_order_delete(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.update_table_status_on_order_delete() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    -- Обновляем статус стола на "свободно" (1)
    UPDATE Tabble
    SET status = 1
    WHERE ID_tabble = OLD.ID_tabble;
    RETURN OLD;
END;
$$;


ALTER FUNCTION public.update_table_status_on_order_delete() OWNER TO postgres;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- TOC entry 224 (class 1259 OID 17363)
-- Name: chef; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.chef (
    id_chef integer NOT NULL,
    number_license character varying(30) NOT NULL,
    fio_chef character varying(50) NOT NULL
);


ALTER TABLE public.chef OWNER TO postgres;

--
-- TOC entry 223 (class 1259 OID 17362)
-- Name: chef_id_chef_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.chef_id_chef_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.chef_id_chef_seq OWNER TO postgres;

--
-- TOC entry 4915 (class 0 OID 0)
-- Dependencies: 223
-- Name: chef_id_chef_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.chef_id_chef_seq OWNED BY public.chef.id_chef;


--
-- TOC entry 226 (class 1259 OID 17371)
-- Name: client; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.client (
    id_client integer NOT NULL,
    phone_client character(11),
    fio_client character varying(50) NOT NULL
);


ALTER TABLE public.client OWNER TO postgres;

--
-- TOC entry 225 (class 1259 OID 17370)
-- Name: client_id_client_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.client_id_client_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.client_id_client_seq OWNER TO postgres;

--
-- TOC entry 4916 (class 0 OID 0)
-- Dependencies: 225
-- Name: client_id_client_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.client_id_client_seq OWNED BY public.client.id_client;


--
-- TOC entry 228 (class 1259 OID 17379)
-- Name: feedback; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.feedback (
    id_feedback integer NOT NULL,
    id_client integer NOT NULL,
    id_manager integer NOT NULL,
    date_feedback date NOT NULL,
    date_answer date,
    answer character varying(500),
    description character varying(500)
);


ALTER TABLE public.feedback OWNER TO postgres;

--
-- TOC entry 227 (class 1259 OID 17378)
-- Name: feedback_id_feedback_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.feedback_id_feedback_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.feedback_id_feedback_seq OWNER TO postgres;

--
-- TOC entry 4917 (class 0 OID 0)
-- Dependencies: 227
-- Name: feedback_id_feedback_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.feedback_id_feedback_seq OWNED BY public.feedback.id_feedback;


--
-- TOC entry 230 (class 1259 OID 17391)
-- Name: manager; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.manager (
    id_manager integer NOT NULL,
    fio_manager character varying(50) NOT NULL
);


ALTER TABLE public.manager OWNER TO postgres;

--
-- TOC entry 229 (class 1259 OID 17390)
-- Name: manager_id_manager_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.manager_id_manager_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.manager_id_manager_seq OWNER TO postgres;

--
-- TOC entry 4918 (class 0 OID 0)
-- Dependencies: 229
-- Name: manager_id_manager_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.manager_id_manager_seq OWNED BY public.manager.id_manager;


--
-- TOC entry 218 (class 1259 OID 17333)
-- Name: menu; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.menu (
    id_dish integer NOT NULL,
    id_manager integer,
    cost_dish numeric(10,2) NOT NULL,
    name_dish character varying(50) NOT NULL
);


ALTER TABLE public.menu OWNER TO postgres;

--
-- TOC entry 217 (class 1259 OID 17332)
-- Name: menu_id_dish_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.menu_id_dish_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.menu_id_dish_seq OWNER TO postgres;

--
-- TOC entry 4919 (class 0 OID 0)
-- Dependencies: 217
-- Name: menu_id_dish_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.menu_id_dish_seq OWNED BY public.menu.id_dish;


--
-- TOC entry 232 (class 1259 OID 17399)
-- Name: order; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public."order" (
    id_order integer NOT NULL,
    id_waiter integer NOT NULL,
    id_tabble integer NOT NULL,
    id_client integer NOT NULL,
    date_order date NOT NULL
);


ALTER TABLE public."order" OWNER TO postgres;

--
-- TOC entry 234 (class 1259 OID 17410)
-- Name: order details; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public."order details" (
    id_order_details integer NOT NULL,
    id_order integer NOT NULL,
    id_dish integer NOT NULL,
    id_chef integer,
    count numeric(15,2) NOT NULL,
    cost_order numeric(10,2) NOT NULL,
    time_ready date NOT NULL
);


ALTER TABLE public."order details" OWNER TO postgres;

--
-- TOC entry 233 (class 1259 OID 17409)
-- Name: order details_id_order_details_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public."order details_id_order_details_seq"
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public."order details_id_order_details_seq" OWNER TO postgres;

--
-- TOC entry 4920 (class 0 OID 0)
-- Dependencies: 233
-- Name: order details_id_order_details_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public."order details_id_order_details_seq" OWNED BY public."order details".id_order_details;


--
-- TOC entry 231 (class 1259 OID 17398)
-- Name: order_id_order_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.order_id_order_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.order_id_order_seq OWNER TO postgres;

--
-- TOC entry 4921 (class 0 OID 0)
-- Dependencies: 231
-- Name: order_id_order_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.order_id_order_seq OWNED BY public."order".id_order;


--
-- TOC entry 220 (class 1259 OID 17342)
-- Name: tabble; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tabble (
    id_tabble integer NOT NULL,
    id_waiter integer,
    status integer DEFAULT 1 NOT NULL,
    CONSTRAINT ckc_status_tabble CHECK (((status >= 1) AND (status <= 2)))
);


ALTER TABLE public.tabble OWNER TO postgres;

--
-- TOC entry 4922 (class 0 OID 0)
-- Dependencies: 220
-- Name: COLUMN tabble.status; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tabble.status IS '1 - Убран
2 - Нужна уборка';


--
-- TOC entry 219 (class 1259 OID 17341)
-- Name: tabble_id_tabble_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tabble_id_tabble_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tabble_id_tabble_seq OWNER TO postgres;

--
-- TOC entry 4923 (class 0 OID 0)
-- Dependencies: 219
-- Name: tabble_id_tabble_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.tabble_id_tabble_seq OWNED BY public.tabble.id_tabble;


--
-- TOC entry 222 (class 1259 OID 17353)
-- Name: waiter; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.waiter (
    id_waiter integer NOT NULL,
    shift character varying(15) DEFAULT '1'::character varying NOT NULL,
    fio_waiter character varying(50) NOT NULL,
    phone_waiter character(11),
    salary money,
    CONSTRAINT ckc_shift_waiter CHECK ((((shift)::text >= '1'::text) AND ((shift)::text <= '3'::text)))
);


ALTER TABLE public.waiter OWNER TO postgres;

--
-- TOC entry 4924 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN waiter.shift; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.waiter.shift IS '1 - не вышел на работу
2 - утренняя
3 - вечерняя';


--
-- TOC entry 221 (class 1259 OID 17352)
-- Name: waiter_id_waiter_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.waiter_id_waiter_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.waiter_id_waiter_seq OWNER TO postgres;

--
-- TOC entry 4925 (class 0 OID 0)
-- Dependencies: 221
-- Name: waiter_id_waiter_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.waiter_id_waiter_seq OWNED BY public.waiter.id_waiter;


--
-- TOC entry 4689 (class 2604 OID 17366)
-- Name: chef id_chef; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.chef ALTER COLUMN id_chef SET DEFAULT nextval('public.chef_id_chef_seq'::regclass);


--
-- TOC entry 4690 (class 2604 OID 17374)
-- Name: client id_client; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client ALTER COLUMN id_client SET DEFAULT nextval('public.client_id_client_seq'::regclass);


--
-- TOC entry 4691 (class 2604 OID 17382)
-- Name: feedback id_feedback; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.feedback ALTER COLUMN id_feedback SET DEFAULT nextval('public.feedback_id_feedback_seq'::regclass);


--
-- TOC entry 4692 (class 2604 OID 17394)
-- Name: manager id_manager; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.manager ALTER COLUMN id_manager SET DEFAULT nextval('public.manager_id_manager_seq'::regclass);


--
-- TOC entry 4684 (class 2604 OID 17336)
-- Name: menu id_dish; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu ALTER COLUMN id_dish SET DEFAULT nextval('public.menu_id_dish_seq'::regclass);


--
-- TOC entry 4693 (class 2604 OID 17402)
-- Name: order id_order; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public."order" ALTER COLUMN id_order SET DEFAULT nextval('public.order_id_order_seq'::regclass);


--
-- TOC entry 4694 (class 2604 OID 17413)
-- Name: order details id_order_details; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public."order details" ALTER COLUMN id_order_details SET DEFAULT nextval('public."order details_id_order_details_seq"'::regclass);


--
-- TOC entry 4685 (class 2604 OID 17345)
-- Name: tabble id_tabble; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tabble ALTER COLUMN id_tabble SET DEFAULT nextval('public.tabble_id_tabble_seq'::regclass);


--
-- TOC entry 4687 (class 2604 OID 17356)
-- Name: waiter id_waiter; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.waiter ALTER COLUMN id_waiter SET DEFAULT nextval('public.waiter_id_waiter_seq'::regclass);


--
-- TOC entry 4898 (class 0 OID 17363)
-- Dependencies: 224
-- Data for Name: chef; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.chef (id_chef, number_license, fio_chef) FROM stdin;
1	1234567	Зубенко Михаил Петрович
2	223312367	Григорьев Валерий Викторович
3	23232112367	Попков Дмитрий Валерьевич
4	22334536367	Галустян Арарат Степанович
5	234234257	Ненахов Александр Михайлович
6	23415184257	Иванов Иван Иванович
\.


--
-- TOC entry 4900 (class 0 OID 17371)
-- Dependencies: 226
-- Data for Name: client; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.client (id_client, phone_client, fio_client) FROM stdin;
264	1234567890	Иванов Иван Иванович
265	79201234567	Иванов Иван Cтепанович
267	79201234569	Сидоров Сидор Сидорович
268	79201234570	Кузнецов Алексей Алексеевич
269	79201234571	Смирнов Дмитрий Дмитриевич
270	79201234572	Попов Николай Николаевич
271	79201234574	Ковалев Сергей Сергеевич
272	79201234575	Тихонов Виктор Викторович
273	79201234576	Морозов Артем Артемович
274	79201234577	Федоров Игорь Игоревич
275	79201234578	Соловьев Денис Денисович
276	79201234579	Григорьев Роман Романович
277	79201234580	Васильев Владислав Владиславович
278	79201234581	Климов Станислав Станиславович
279	79201234582	Михайлов Алексей Алексеевич
280	79201234583	Сергеев Илья Ильич
281	79201234584	Дмитриев Артем Артемович
282	79201234585	Ковалев Николай Николаевич
283	79201234586	Савельев Павел Павлович
284	79201234587	Сидоренко Виктор Викторович
285	79201234588	Кузьмин Роман Романович
286	79201234589	Лебедев Игорь Игоревич
287	79201234590	Смирнов Денис Денисович
288	79201234591	Петрова Анна Сергеевна
289	79201234592	Иванова Мария Ивановна
290	79041234567	Сидорова Ольга Викторовна
291	79041234568	Кузнецова Наталья Алексеевна
292	79041234569	Смирнова Екатерина Дмитриевна
293	79041234570	Попова Татьяна Николаевна
294	79041234571	Лебедева Светлана Андреевна
295	79041234572	Ковалёва Юлия Сергеевна
296	79041234573	Тихонова Дарья Игоревна
297	79041234574	Морозова Анастасия Владимировна
298	79041234575	Федорова Виктория Игоревна
299	79041234576	Соловьева Ксения Александровна
300	79041234577	Григорьева Полина Романовна
301	79041234578	Васильева Елена Николаевна
302	79041234579	Климова Марина Станиславовна
303	79041234580	Михайлова Вероника Ильинична
304	79041234581	Сергеевна Ирина Алексеевна
305	79041234582	Дмитриева Оксана Павловна
306	79041234583	Ковалёва Надежда Викторовна
307	79041234584	Савельева Алина Сергеевна
308	79041234585	Сидоренко Лилия Викторовна
309	79041234586	Кузьмина Светлана Игоревна
310	79041234587	Лебедева Татьяна Владимировна
311	79041234588	Смирнова Анастасия Игоревна
312	79041234589	Петрова Ольга Сергеевна
313	79041234590	Иванова Наталья Ивановна
314	79041234591	Сидорова Кристина Викторовна
315	79041234592	Кузнецова Дарья Алексеевна
316	79205120707	Чуваков Владислав Владимирович
\.


--
-- TOC entry 4902 (class 0 OID 17379)
-- Dependencies: 228
-- Data for Name: feedback; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.feedback (id_feedback, id_client, id_manager, date_feedback, date_answer, answer, description) FROM stdin;
2	265	2	2023-10-03	2023-10-04	Ответ 2	Описание отзыва 2
4	267	3	2023-10-07	2023-10-08	Ответ 4	Описание отзыва 4
5	268	2	2023-10-09	2023-10-10	Ответ 5	Описание отзыва 5
6	269	4	2023-10-11	2023-10-12	Ответ 6	Описание отзыва 6
7	270	1	2023-10-13	2023-10-14	Ответ 7	Описание отзыва 7
8	271	3	2023-10-15	2023-10-16	Ответ 8	Описание отзыва 8
9	272	2	2023-10-17	2023-10-18	Ответ 9	Описание отзыва 9
10	273	4	2023-10-19	2023-10-20	Ответ 10	Описание отзыва 10
11	274	1	2023-10-21	2023-10-22	Ответ 11	Описание отзыва 11
12	275	3	2023-10-23	2023-10-24	Ответ 12	Описание отзыва 12
13	276	2	2023-10-25	2023-10-26	Ответ 13	Описание отзыва 13
14	277	4	2023-10-27	2023-10-28	Ответ 14	Описание отзыва 14
15	278	1	2023-10-29	2023-10-30	Ответ 15	Описание отзыва 15
1	264	1	2023-10-01	2023-10-02	Ответ 1	Отзыв создан для 3-его ЗАДАНИЯ
16	264	1	2023-10-01	2023-10-02	Ответ на отзыв	Отзыв создан для 3-его ЗАДАНИЯ
\.


--
-- TOC entry 4904 (class 0 OID 17391)
-- Dependencies: 230
-- Data for Name: manager; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.manager (id_manager, fio_manager) FROM stdin;
1	Иванов Иван Иванович
3	Сидоров Сидор Сидорович
4	Кузнецов Алексей Алексеевич
2	Валера
\.


--
-- TOC entry 4892 (class 0 OID 17333)
-- Dependencies: 218
-- Data for Name: menu; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.menu (id_dish, id_manager, cost_dish, name_dish) FROM stdin;
11	3	9.00	Брокли с сыром
12	3	12.50	Брокли с сыром
13	3	8.00	Брокли с сыром
14	3	11.50	Брокли с сыром
15	3	10.25	Брокли с сыром
16	4	13.75	TESTING2
17	4	9.80	TESTING1
18	4	14.00	TESTING2
19	4	7.50	TESTING1
20	4	12.00	ВСЕ ЕЩЕ ТЕСТ
\.


--
-- TOC entry 4906 (class 0 OID 17399)
-- Dependencies: 232
-- Data for Name: order; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public."order" (id_order, id_waiter, id_tabble, id_client, date_order) FROM stdin;
2	4	3	264	2024-12-12
3	4	3	264	2024-12-12
4	4	3	264	2023-12-12
5	5	12	274	2023-12-12
6	4	3	264	2025-02-22
7	6	6	269	2023-10-06
8	7	7	270	2023-10-07
9	8	8	271	2023-10-08
10	9	9	272	2023-10-09
11	10	10	273	2023-10-10
12	11	11	274	2023-10-11
13	6	5	264	2024-12-12
14	7	4	268	2024-12-12
15	7	4	268	2024-12-12
16	7	4	268	2024-12-12
17	7	4	268	2024-12-12
18	7	4	268	2024-12-12
\.


--
-- TOC entry 4908 (class 0 OID 17410)
-- Dependencies: 234
-- Data for Name: order details; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public."order details" (id_order_details, id_order, id_dish, id_chef, count, cost_order, time_ready) FROM stdin;
24	6	11	1	2.00	22.00	2023-10-01
25	6	12	2	1.00	16.00	2023-10-01
26	7	13	3	2.00	28.00	2023-10-01
\.


--
-- TOC entry 4894 (class 0 OID 17342)
-- Dependencies: 220
-- Data for Name: tabble; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.tabble (id_tabble, id_waiter, status) FROM stdin;
3	2	1
6	5	1
15	2	1
16	3	1
17	4	1
18	5	1
5	4	2
4	3	1
7	6	2
8	7	2
9	8	2
10	9	2
11	10	2
12	11	2
13	14	2
14	15	2
19	6	2
20	7	2
21	8	2
22	9	2
23	10	2
24	11	2
25	14	2
26	15	2
\.


--
-- TOC entry 4896 (class 0 OID 17353)
-- Dependencies: 222
-- Data for Name: waiter; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.waiter (id_waiter, shift, fio_waiter, phone_waiter, salary) FROM stdin;
15	3	Чуваков Петр Петрович	79001234568	\N
2	1	Иванов Иван Иванович	79001234567	3000.00
3	2	Петров Петр Петрович	79001234568	4000.00
4	1	Сидоров Сидор Сидорович	79001234569	3000.00
5	2	Кузнецов Алексей Алексеевич	79001234570	4000.00
6	1	Смирнов Дмитрий Дмитриевич	79001234571	3000.00
7	2	Попов Николай Николаевич	79001234572	4000.00
8	1	Лебедев Андрей Андреевич	79001234573	3000.00
9	2	Ковалев Сергей Сергеевич	79001234574	4000.00
10	1	Тихонов Виктор Викторович	79001234575	3000.00
11	2	Морозов Артем Артемович	79001234576	4000.00
14	1	Иванов Иван Иванович	79001234567	3000.00
\.


--
-- TOC entry 4926 (class 0 OID 0)
-- Dependencies: 223
-- Name: chef_id_chef_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.chef_id_chef_seq', 1, false);


--
-- TOC entry 4927 (class 0 OID 0)
-- Dependencies: 225
-- Name: client_id_client_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.client_id_client_seq', 316, true);


--
-- TOC entry 4928 (class 0 OID 0)
-- Dependencies: 227
-- Name: feedback_id_feedback_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.feedback_id_feedback_seq', 16, true);


--
-- TOC entry 4929 (class 0 OID 0)
-- Dependencies: 229
-- Name: manager_id_manager_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.manager_id_manager_seq', 4, true);


--
-- TOC entry 4930 (class 0 OID 0)
-- Dependencies: 217
-- Name: menu_id_dish_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.menu_id_dish_seq', 20, true);


--
-- TOC entry 4931 (class 0 OID 0)
-- Dependencies: 233
-- Name: order details_id_order_details_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public."order details_id_order_details_seq"', 26, true);


--
-- TOC entry 4932 (class 0 OID 0)
-- Dependencies: 231
-- Name: order_id_order_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.order_id_order_seq', 22, true);


--
-- TOC entry 4933 (class 0 OID 0)
-- Dependencies: 219
-- Name: tabble_id_tabble_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.tabble_id_tabble_seq', 26, true);


--
-- TOC entry 4934 (class 0 OID 0)
-- Dependencies: 221
-- Name: waiter_id_waiter_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.waiter_id_waiter_seq', 21, true);


--
-- TOC entry 4729 (class 2606 OID 17415)
-- Name: order details PK_ORDER DETAILS; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public."order details"
    ADD CONSTRAINT "PK_ORDER DETAILS" PRIMARY KEY (id_order_details);


--
-- TOC entry 4710 (class 2606 OID 17368)
-- Name: chef pk_chef; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.chef
    ADD CONSTRAINT pk_chef PRIMARY KEY (id_chef);


--
-- TOC entry 4713 (class 2606 OID 17376)
-- Name: client pk_client; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client
    ADD CONSTRAINT pk_client PRIMARY KEY (id_client);


--
-- TOC entry 4718 (class 2606 OID 17386)
-- Name: feedback pk_feedback; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.feedback
    ADD CONSTRAINT pk_feedback PRIMARY KEY (id_feedback);


--
-- TOC entry 4721 (class 2606 OID 17396)
-- Name: manager pk_manager; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.manager
    ADD CONSTRAINT pk_manager PRIMARY KEY (id_manager);


--
-- TOC entry 4700 (class 2606 OID 17338)
-- Name: menu pk_menu; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu
    ADD CONSTRAINT pk_menu PRIMARY KEY (id_dish);


--
-- TOC entry 4725 (class 2606 OID 17404)
-- Name: order pk_order; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public."order"
    ADD CONSTRAINT pk_order PRIMARY KEY (id_order);


--
-- TOC entry 4703 (class 2606 OID 17349)
-- Name: tabble pk_tabble; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tabble
    ADD CONSTRAINT pk_tabble PRIMARY KEY (id_tabble);


--
-- TOC entry 4706 (class 2606 OID 17360)
-- Name: waiter pk_waiter; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.waiter
    ADD CONSTRAINT pk_waiter PRIMARY KEY (id_waiter);


--
-- TOC entry 4714 (class 1259 OID 17389)
-- Name: analyzes_fk; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX analyzes_fk ON public.feedback USING btree (id_manager);


--
-- TOC entry 4708 (class 1259 OID 17369)
-- Name: chef_pk; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX chef_pk ON public.chef USING btree (id_chef);


--
-- TOC entry 4701 (class 1259 OID 17351)
-- Name: cleaning_fk; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX cleaning_fk ON public.tabble USING btree (id_waiter);


--
-- TOC entry 4711 (class 1259 OID 17377)
-- Name: client_pk; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX client_pk ON public.client USING btree (id_client);


--
-- TOC entry 4730 (class 1259 OID 17417)
-- Name: compose_fk; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX compose_fk ON public."order details" USING btree (id_order);


--
-- TOC entry 4697 (class 1259 OID 17340)
-- Name: constitute2_fk; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX constitute2_fk ON public.menu USING btree (id_manager);


--
-- TOC entry 4731 (class 1259 OID 17419)
-- Name: cook_fk; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX cook_fk ON public."order details" USING btree (id_chef);


--
-- TOC entry 4732 (class 1259 OID 17418)
-- Name: corresponds_fk; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX corresponds_fk ON public."order details" USING btree (id_dish);


--
-- TOC entry 4722 (class 1259 OID 17408)
-- Name: designs_fk; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX designs_fk ON public."order" USING btree (id_client);


--
-- TOC entry 4715 (class 1259 OID 17387)
-- Name: feedback_pk; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX feedback_pk ON public.feedback USING btree (id_feedback);


--
-- TOC entry 4716 (class 1259 OID 17388)
-- Name: leaves_fk; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX leaves_fk ON public.feedback USING btree (id_client);


--
-- TOC entry 4719 (class 1259 OID 17397)
-- Name: manager_pk; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX manager_pk ON public.manager USING btree (id_manager);


--
-- TOC entry 4698 (class 1259 OID 17339)
-- Name: menu_pk; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX menu_pk ON public.menu USING btree (id_dish);


--
-- TOC entry 4733 (class 1259 OID 17416)
-- Name: order details_PK; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX "order details_PK" ON public."order details" USING btree (id_order_details);


--
-- TOC entry 4723 (class 1259 OID 17405)
-- Name: order_pk; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX order_pk ON public."order" USING btree (id_order);


--
-- TOC entry 4726 (class 1259 OID 17407)
-- Name: reserves_fk; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX reserves_fk ON public."order" USING btree (id_tabble);


--
-- TOC entry 4727 (class 1259 OID 17406)
-- Name: service_fk; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX service_fk ON public."order" USING btree (id_waiter);


--
-- TOC entry 4704 (class 1259 OID 17350)
-- Name: tabble_pk; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX tabble_pk ON public.tabble USING btree (id_tabble);


--
-- TOC entry 4707 (class 1259 OID 17361)
-- Name: waiter_pk; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX waiter_pk ON public.waiter USING btree (id_waiter);


--
-- TOC entry 4744 (class 2620 OID 17564)
-- Name: order update_table_status_on_order_delete; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER update_table_status_on_order_delete AFTER DELETE ON public."order" FOR EACH ROW EXECUTE FUNCTION public.update_table_status_on_order_delete();


--
-- TOC entry 4745 (class 2620 OID 17562)
-- Name: order update_table_status_on_order_insert; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER update_table_status_on_order_insert AFTER INSERT ON public."order" FOR EACH ROW EXECUTE FUNCTION public.update_table_status();


--
-- TOC entry 4741 (class 2606 OID 17455)
-- Name: order details FK_ORDER DE_COMPOSE_ORDER; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public."order details"
    ADD CONSTRAINT "FK_ORDER DE_COMPOSE_ORDER" FOREIGN KEY (id_order) REFERENCES public."order"(id_order) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 4742 (class 2606 OID 17460)
-- Name: order details FK_ORDER DE_COOK_CHEF; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public."order details"
    ADD CONSTRAINT "FK_ORDER DE_COOK_CHEF" FOREIGN KEY (id_chef) REFERENCES public.chef(id_chef) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- TOC entry 4743 (class 2606 OID 17465)
-- Name: order details FK_ORDER DE_CORRESPON_MENU; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public."order details"
    ADD CONSTRAINT "FK_ORDER DE_CORRESPON_MENU" FOREIGN KEY (id_dish) REFERENCES public.menu(id_dish) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- TOC entry 4736 (class 2606 OID 17430)
-- Name: feedback fk_feedback_analyzes_manager; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.feedback
    ADD CONSTRAINT fk_feedback_analyzes_manager FOREIGN KEY (id_manager) REFERENCES public.manager(id_manager) ON UPDATE RESTRICT ON DELETE SET NULL;


--
-- TOC entry 4737 (class 2606 OID 17435)
-- Name: feedback fk_feedback_leaves_client; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.feedback
    ADD CONSTRAINT fk_feedback_leaves_client FOREIGN KEY (id_client) REFERENCES public.client(id_client) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- TOC entry 4734 (class 2606 OID 17420)
-- Name: menu fk_menu_constitut_manager; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu
    ADD CONSTRAINT fk_menu_constitut_manager FOREIGN KEY (id_manager) REFERENCES public.manager(id_manager) ON UPDATE RESTRICT ON DELETE SET DEFAULT;


--
-- TOC entry 4738 (class 2606 OID 17440)
-- Name: order fk_order_designs_client; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public."order"
    ADD CONSTRAINT fk_order_designs_client FOREIGN KEY (id_client) REFERENCES public.client(id_client) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 4739 (class 2606 OID 17445)
-- Name: order fk_order_reserves_tabble; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public."order"
    ADD CONSTRAINT fk_order_reserves_tabble FOREIGN KEY (id_tabble) REFERENCES public.tabble(id_tabble) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 4740 (class 2606 OID 17450)
-- Name: order fk_order_service_waiter; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public."order"
    ADD CONSTRAINT fk_order_service_waiter FOREIGN KEY (id_waiter) REFERENCES public.waiter(id_waiter) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- TOC entry 4735 (class 2606 OID 17425)
-- Name: tabble fk_tabble_cleaning_waiter; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tabble
    ADD CONSTRAINT fk_tabble_cleaning_waiter FOREIGN KEY (id_waiter) REFERENCES public.waiter(id_waiter) ON UPDATE CASCADE ON DELETE SET NULL;


-- Completed on 2025-04-22 02:25:07

--
-- PostgreSQL database dump complete
--